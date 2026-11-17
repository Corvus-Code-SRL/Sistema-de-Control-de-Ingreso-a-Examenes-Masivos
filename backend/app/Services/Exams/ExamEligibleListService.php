<?php

namespace App\Services\Exams;

use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterReaderResolver;
use App\Services\Exams\Importers\EligibleListCurrentResult;
use App\Services\Exams\Importers\EligibleListDatabaseMatch;
use App\Services\Exams\Importers\EligibleListDatabaseMatcher;
use App\Services\Exams\Importers\EligibleListImportResult;
use App\Services\Exams\Importers\EligibleListPreviewResult;
use App\Services\Exams\Importers\EligibleListPreviewStore;
use App\Services\Security\AuditLogService;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lista de habilitados de un examen (HU-028).
 *
 * Flujo principal:
 * - current(): lista completa, editable solo en PROGRAMADO.
 * - saveChanges(): PUT /habilitados, aplica cambios en transacción.
 *
 * Camino secundario (importación):
 * - preview(): sube un archivo y muestra qué pasaría (no toca BD).
 * - confirm(): valida el token y devuelve "cambios pendientes"; no
 *   aplica nada. El cliente los muestra y luego llama a saveChanges().
 *
 * Reglas:
 * - Solo PROGRAMADO permite preview, confirm y saveChanges (409 si no).
 * - current() se permite en cualquier estado (404 / 403 si no es del
 *   docente).
 * - Siempre hay un estado por estudiante. Por defecto HABILITADO.
 * - CASO ESPECIAL se preserva: HU-028 no puede quitarlo. Si el cliente
 *   intenta cambiarlo, 422.
 * - El archivo solo trae cod_sis. Los del archivo se proponen
 *   HABILITADO; los de la nómina que no están en el archivo se proponen
 *   NO_HABILITADO.
 * - Archivo vacío: se propone NO_HABILITADO a todos los de la nómina.
 */
class ExamEligibleListService
{
    private const HABILITADO     = 'HABILITADO';

    private const NO_HABILITADO  = 'NO_HABILITADO';

    private const CASO_ESPECIAL  = 'CASO ESPECIAL';

    private ExamEligibleListAccess $access;

    private StudentRosterReaderResolver $readerResolver;

    private StudentRosterAnalyzer $analyzer;

    private EligibleListDatabaseMatcher $matcher;

    private EligibleListPreviewStore $previewStore;

    private AuditLogService $auditLog;

    private ExamRosterService $roster;

    public function __construct(
        ExamEligibleListAccess $access,
        StudentRosterReaderResolver $readerResolver,
        StudentRosterAnalyzer $analyzer,
        EligibleListDatabaseMatcher $matcher,
        EligibleListPreviewStore $previewStore,
        AuditLogService $auditLog,
        ExamRosterService $roster
    ) {
        $this->access = $access;
        $this->readerResolver = $readerResolver;
        $this->analyzer = $analyzer;
        $this->matcher = $matcher;
        $this->previewStore = $previewStore;
        $this->auditLog = $auditLog;
        $this->roster = $roster;
    }

    public function preview(
        int $examId,
        string $path,
        string $extension,
        string $teacherId
    ): EligibleListPreviewResult {
        $this->access->getAvailableForWrite($examId, $teacherId);

        $reader = $this->readerResolver->resolve($extension);

        $analysis = $this->analyzer->analyze($reader->read($path));

        $matches = $this->matcher->classify($examId, $analysis);

        $sisCodesInFile = $this->sisCodesInFile($analysis);

        $faltantes = $this->matcher->countMissing($examId, $sisCodesInFile);

        $token = $this->previewStore->store($examId, $teacherId, $analysis);

        return new EligibleListPreviewResult(
            $token,
            $analysis->totalRows(),
            $analysis->validRows(),
            $analysis->inconsistentRows(),
            $faltantes,
            $this->buildPreviewRows($analysis, $matches)
        );
    }

    /**
     * Valida el token y devuelve los cambios pendientes que el cliente
     * deberá aplicar vía PUT /habilitados. NO escribe en BD.
     */
    public function confirm(
        int $examId,
        string $token,
        string $teacherId,
        string $motivo,
        ?string $detalle
    ): EligibleListImportResult {
        $this->access->getAvailableForWrite($examId, $teacherId);

        $preview = $this->previewStore->find($token);

        if ($preview === null) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview de la lista no existe o ha expirado.',
                404
            );
        }

        if ($preview->teacherId() !== $teacherId) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview no pertenece al docente actual.',
                403
            );
        }

        if ($preview->examId() !== $examId) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview no corresponde al examen indicado.',
                422
            );
        }

        // Valida motivo/detalle antes de construir cambios.
        $observacion = MotivoNoHabilitacion::toObservation($motivo, $detalle);

        $analysis = $this->analyzer->analyze($preview->rows());

        $matches = $this->matcher->classify($examId, $analysis);

        $matchedStudentIds = [];

        foreach ($matches as $match) {
            if (
                $match->status() === EligibleListDatabaseMatch::MATCHED
                && $match->studentId() !== null
            ) {
                $matchedStudentIds[$match->studentId()] = true;
            }
        }

        $noEncontrados = 0;

        foreach ($matches as $match) {
            if ($match->status() === EligibleListDatabaseMatch::NOT_FOUND_IN_EXAM) {
                $noEncontrados++;
            }
        }

        // Nómina completa del examen (para proponer NO_HABILITADO a los
        // que no están en el archivo).
        $roster = $this->roster->expected($examId)->get([
            'estudiante.id_estudiante',
            'estudiante.cod_sis',
            'estudiante.nombre',
            'estudiante.apellido_paterno',
            'estudiante.apellido_materno',
            'estado_habilitacion',
        ]);

        $cambios = [];

        foreach ($roster as $row) {
            $id = (int) $row->id_estudiante;

            if ($row->estado_habilitacion === self::CASO_ESPECIAL) {
                // No se toca: HU-028 no quita CASO ESPECIAL.
                continue;
            }

            if (isset($matchedStudentIds[$id])) {
                if ($row->estado_habilitacion !== self::HABILITADO) {
                    $cambios[] = [
                        'id_estudiante' => $id,
                        'codigo_sis' => (string) $row->cod_sis,
                        'nombre_completo' => $this->fullName($row),
                        'estado_habilitacion' => self::HABILITADO,
                        'motivo' => null,
                        'detalle' => null,
                    ];
                }

                continue;
            }

            // No está en el archivo: proponer NO_HABILITADO.
            $cambios[] = [
                'id_estudiante' => $id,
                'codigo_sis' => (string) $row->cod_sis,
                'nombre_completo' => $this->fullName($row),
                'estado_habilitacion' => self::NO_HABILITADO,
                'motivo' => $motivo,
                'detalle' => MotivoNoHabilitacion::requiresDetail($motivo) ? $detalle : null,
            ];
        }

        usort(
            $cambios,
            static fn (array $a, array $b): int =>
                strcmp($a['codigo_sis'], $b['codigo_sis'])
        );

        $this->previewStore->forget($token);

        return new EligibleListImportResult(
            $analysis->totalRows(),
            $analysis->inconsistentRows(),
            $noEncontrados,
            $cambios
        );
    }

    /**
     * Aplica los cambios en una transacción.
     *
     * @param array<int, array{
     *     id_estudiante: int,
     *     estado_habilitacion: string,
     *     motivo?: ?string,
     *     detalle?: ?string
     * }> $cambios
     */
    public function saveChanges(
        int $examId,
        array $cambios,
        string $teacherId
    ): void {
        $this->access->getAvailableForWrite($examId, $teacherId);

        if ($cambios === []) {
            return;
        }

        DB::transaction(function () use ($examId, $cambios, $teacherId): void {
            $examRow = DB::table('examen')
                ->where('id_examen', $examId)
                ->lockForUpdate()
                ->first(['id_examen', 'estado']);

            if ($examRow === null) {
                throw new ModelNotFoundException('No existe el examen indicado.');
            }

            if ($examRow->estado !== Exam::PROGRAMADO) {
                throw new ExamStateException(
                    'La lista de habilitados solo puede modificarse mientras el examen está PROGRAMADO.'
                );
            }

            $requestedIds = array_map(
                static fn (array $c): int => (int) $c['id_estudiante'],
                $cambios
            );
            $requestedIds = array_values(array_unique($requestedIds));

            // La nómina del examen vive en grupo_estudiante ⋈ grupo_examen.
            // expected() deriva esa nómina con LEFT JOIN a examen_estudiante,
            // así que un estudiante sin fila también aparece (como HABILITADO).
            // Validar contra examen_estudiante rechazaría a esos estudiantes
            // con "no pertenecen al examen" aunque sí pertenezcan.
            $existing = $this->roster->expected($examId)
                ->whereIn('estudiante.id_estudiante', $requestedIds)
                ->get([
                    'estudiante.id_estudiante',
                    'estado_habilitacion',
                    'observacion',
                    'id_grupo',
                ])
                ->keyBy('id_estudiante');

            $missing = array_diff($requestedIds, $existing->keys()->map(
                static fn ($id): int => (int) $id
            )->all());

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'cambios' => [
                        'Uno o más estudiantes no pertenecen al examen indicado.',
                    ],
                ]);
            }

            $casoEspecialIds = $existing
                ->filter(static fn ($row): bool => $row->estado_habilitacion === self::CASO_ESPECIAL)
                ->keys()
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($casoEspecialIds !== []) {
                throw ValidationException::withMessages([
                    'cambios' => [
                        'No se puede modificar a un estudiante en CASO ESPECIAL desde esta vista.',
                    ],
                ]);
            }

            $before = [];

            foreach ($existing as $row) {
                $before[(int) $row->id_estudiante] = [
                    'estado_habilitacion' => $row->estado_habilitacion,
                    'observacion' => $row->observacion,
                ];
            }

            $after = $before;

            $gruposPorEstudiante = $this->roster->expected($examId)
                ->whereIn('estudiante.id_estudiante', $requestedIds)
                ->pluck('id_grupo', 'estudiante.id_estudiante');

            foreach ($cambios as $cambio) {
                $id = (int) $cambio['id_estudiante'];
                $estado = (string) $cambio['estado_habilitacion'];

                // Si el estudiante no tenía fila en examen_estudiante (nómina
                // sin HU-028 previa), la creamos con el id_grupo de la nómina
                // antes del update. insertOrIgnore no pisa una fila existente
                // (creada por HU-031 o por el control de ingreso).
                $idGrupo = isset($existing[$id]) ? (int) $existing[$id]->id_grupo : null;

                if ($idGrupo === null) {
                    throw ValidationException::withMessages([
                        'cambios' => [
                            'Uno o más estudiantes no pertenecen al examen indicado.',
                        ],
                    ]);
                }

                $this->roster->ensureRow($examId, $id, $idGrupo);

                if ($estado === self::HABILITADO) {
                    $this->roster->ensureRow($examId, $id, (int) $gruposPorEstudiante[$id]);
                    DB::table('examen_estudiante')
                        ->where('id_examen', $examId)
                        ->where('id_estudiante', $id)
                        ->update(['estado_habilitacion' => self::HABILITADO]);

                    $after[$id]['estado_habilitacion'] = self::HABILITADO;
                    // La observación no se limpia (por simplicidad, ver P-a).

                    continue;
                }

                $observacion = MotivoNoHabilitacion::toObservation(
                    (string) ($cambio['motivo'] ?? ''),
                    isset($cambio['detalle']) ? (string) $cambio['detalle'] : null
                );

                $this->roster->ensureRow($examId, $id, (int) $gruposPorEstudiante[$id]);
                DB::table('examen_estudiante')
                    ->where('id_examen', $examId)
                    ->where('id_estudiante', $id)
                    ->update([
                        'estado_habilitacion' => self::NO_HABILITADO,
                        'observacion' => $observacion,
                    ]);

                $after[$id]['estado_habilitacion'] = self::NO_HABILITADO;
                $after[$id]['observacion'] = $observacion;
            }

            $this->auditLog->registrar(
                'GUARDAR_HABILITADOS',
                'examen_estudiante',
                [
                    'id_examen' => $examId,
                    'cambios' => $before,
                ],
                [
                    'id_examen' => $examId,
                    'cambios' => $after,
                ],
                $teacherId
            );
        });
    }

    public function current(int $examId, string $teacherId): EligibleListCurrentResult
    {
        $exam = $this->access->getAvailableForRead($examId, $teacherId);

        $editable = $exam->estado === Exam::PROGRAMADO;

        $rows = $this->roster->expected($examId)
            ->orderBy('estudiante.apellido_paterno')
            ->orderBy('estudiante.apellido_materno')
            ->orderBy('estudiante.nombre')
            ->get([
                'estudiante.id_estudiante',
                'estudiante.cod_sis',
                'estudiante.nombre',
                'estudiante.apellido_paterno',
                'estudiante.apellido_materno',
                'id_grupo',
                'num_grupo',
                'estado_habilitacion',
                'observacion',
            ]);

        $estudiantes = [];

        foreach ($rows as $row) {
            $motivo = MotivoNoHabilitacion::fromObservation(
                $row->observacion !== null ? (string) $row->observacion : null
            );

            $estudiantes[] = [
                'id_estudiante' => (int) $row->id_estudiante,
                'codigo_sis' => (string) $row->cod_sis,
                'nombre_completo' => $this->fullName($row),
                'id_grupo' => (int) $row->id_grupo,
                'nombre_grupo' => 'Grupo ' . $row->num_grupo,
                'estado_habilitacion' => $row->estado_habilitacion,
                'caso_especial' => $row->estado_habilitacion === self::CASO_ESPECIAL,
                'motivo' => $motivo['motivo'] ?? null,
                'detalle' => $motivo['detalle'] ?? null,
            ];
        }

        return new EligibleListCurrentResult($editable, $exam->estado, $estudiantes);
    }

        /**
     * Exámenes del docente para el selector de HU-028.
     *
     * Solo PROGRAMADO y EN_INGRESO. Sin filtro de período activo: un examen
     * de un período anterior sigue siendo válido para cargar la lista si
     * aún no abrió el control de ingreso. Sin paginación: el docente tiene
     * pocos exámenes en esos dos estados. Ordenados por fecha ascendente
     * (los más próximos primero), luego por hora de inicio, luego por id.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam>
     */
    public function examsForSelector(string $teacherId): \Illuminate\Database\Eloquent\Collection
    {
        return Exam::query()
            ->with(['subject', 'career', 'groups'])
            ->where('id_usuario_docente', $teacherId)
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_CURSO])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->get();
    }

    /**
     * @param array<int, EligibleListDatabaseMatch> $matches
     * @return array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     status: string,
     *     errors: array<int, string>,
     *     student_name: ?string,
     *     group_name: ?string
     * }>
     */
    private function buildPreviewRows(
        StudentRosterAnalysisResult $analysis,
        array $matches
    ): array {
        $matchesByRowNumber = [];

        foreach ($matches as $match) {
            $matchesByRowNumber[
                $match->rowAnalysis()->row()->rowNumber()
            ] = $match;
        }

        $rows = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            $row = $rowAnalysis->row();

            if (!$rowAnalysis->isValid()) {
                $status = EligibleListPreviewResult::INCONSISTENT;
                $studentName = null;
                $groupName = null;
            } else {
                $match = $matchesByRowNumber[$row->rowNumber()] ?? null;

                if ($match === null) {
                    throw new RuntimeException(
                        'Una fila válida no pudo clasificarse contra el examen.'
                    );
                }

                $status = $match->status() === EligibleListDatabaseMatch::MATCHED
                    ? EligibleListPreviewResult::MATCHED
                    : EligibleListPreviewResult::NOT_FOUND_IN_EXAM;

                $studentName = $match->studentName();
                $groupName = $match->groupName();
            }

            $rows[] = [
                'row_number' => $row->rowNumber(),
                'sis_code' => $row->sisCode(),
                'last_names' => $row->lastNames(),
                'first_names' => $row->firstNames(),
                'status' => $status,
                'errors' => $rowAnalysis->errors(),
                'student_name' => $studentName,
                'group_name' => $groupName,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function sisCodesInFile(StudentRosterAnalysisResult $analysis): array
    {
        $codes = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            if (!$rowAnalysis->isValid()) {
                continue;
            }

            $sisCode = $rowAnalysis->row()->sisCode();

            if ($sisCode !== null) {
                $codes[] = $sisCode;
            }
        }

        return array_values(array_unique($codes));
    }

    private function fullName(object $row): string
    {
        $parts = array_filter([
            (string) $row->apellido_paterno,
            $row->apellido_materno !== null ? (string) $row->apellido_materno : null,
            (string) $row->nombre,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return implode(' ', $parts);
    }
}
