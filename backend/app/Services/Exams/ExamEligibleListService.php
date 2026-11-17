<?php

namespace App\Services\Exams;

use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Models\Exam;
use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterReaderResolver;
use App\Services\Exams\Importers\EligibleListCurrentResult;
use App\Services\Exams\Importers\EligibleListDatabaseMatch;
use App\Services\Exams\Importers\EligibleListDatabaseMatcher;
use App\Services\Exams\Importers\EligibleListImportResult;
use App\Services\Exams\Importers\EligibleListPreview;
use App\Services\Exams\Importers\EligibleListPreviewResult;
use App\Services\Exams\Importers\EligibleListPreviewStore;
use App\Services\Security\AuditLogService;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Lista de habilitados de un examen (HU-028).
 *
 * Camino manual:
 * - current(): lista completa; editable solo dentro de la ventana de escritura
 *   (PROGRAMADO con el control de ingreso sin abrir, ver ExamEligibleListAccess).
 * - saveChanges(): PUT /habilitados, aplica cambios puntuales en una transacción.
 *
 * Camino por archivo:
 * - preview(): sube un archivo y muestra qué pasaría (no toca BD).
 * - replaceFromUpload(): confirma el preview y reemplaza la lista completa en
 *   una sola transacción, con la fila del examen bloqueada.
 *
 * Reglas:
 * - Fuera de la ventana de escritura, preview, confirm y PUT responden 409.
 * - current() se permite en cualquier estado (404 / 403 si no es del docente).
 * - Siempre hay un estado por estudiante. Por defecto HABILITADO.
 * - Los estudiantes que ya están en CASO ESPECIAL no se tocan: desde esta
 *   vista no se pueden cambiar (422 en el PUT, se omiten al reemplazar).
 * - El archivo solo trae cod_sis. Los del archivo quedan HABILITADO y los de la
 *   nómina que no figuran quedan NO_HABILITADO con un motivo fijo.
 * - Archivo vacío: todos los de la nómina quedan NO_HABILITADO.
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
     * Confirma el preview y reemplaza la lista completa del examen.
     *
     * Todo ocurre en una sola transacción con la fila del examen bloqueada: la ventana de escritura
     * se vuelve a comprobar bajo el bloqueo y el token solo se consume si la transacción termina bien.
     */
    public function replaceFromUpload(int $examId, string $token, string $teacherId): EligibleListImportResult
    {
        $this->access->getAvailableForWrite($examId, $teacherId);

        $this->findPreviewFor($token, $examId, $teacherId);

        $result = DB::transaction(function () use ($examId, $token, $teacherId): EligibleListImportResult {
            $this->access->lockForWrite($examId, $teacherId);

            $preview = $this->findPreviewFor($token, $examId, $teacherId);

            $analysis = $this->analyzer->analyze($preview->rows());
            $matches = $this->matcher->classify($examId, $analysis);

            $matchedStudentIds = [];
            $noEncontrados = 0;

            foreach ($matches as $match) {
                if ($match->status() === EligibleListDatabaseMatch::MATCHED && $match->studentId() !== null) {
                    $matchedStudentIds[$match->studentId()] = true;
                } elseif ($match->status() === EligibleListDatabaseMatch::NOT_FOUND_IN_EXAM) {
                    $noEncontrados++;
                }
            }

            $roster = $this->roster->expected($examId)->get([
                'estudiante.id_estudiante',
                'estudiante.cod_sis',
                'estudiante.nombre',
                'estudiante.apellido_paterno',
                'estudiante.apellido_materno',
                'id_grupo',
                'estado_habilitacion',
                'observacion',
            ]);

            $targets = [];
            $groupIds = [];
            $before = [];
            $habilitados = 0;
            $noHabilitados = [];

            foreach ($roster as $row) {
                if ($row->estado_habilitacion === self::CASO_ESPECIAL) {
                    continue;
                }

                $id = (int) $row->id_estudiante;
                $enabled = isset($matchedStudentIds[$id]);
                $newState = $enabled ? self::HABILITADO : self::NO_HABILITADO;
                $newObservation = $enabled ? null : MotivoNoHabilitacion::NO_FIGURA_EN_LISTA;

                if ($enabled) {
                    $habilitados++;
                } else {
                    $noHabilitados[] = [
                        'id_estudiante' => $id,
                        'codigo_sis' => (string) $row->cod_sis,
                        'nombre_completo' => $this->fullName($row),
                    ];
                }

                if ($row->estado_habilitacion === $newState && $row->observacion === $newObservation) {
                    continue;
                }

                $targets[$id] = ['estado_habilitacion' => $newState, 'observacion' => $newObservation];
                $groupIds[$id] = (int) $row->id_grupo;
                $before[$id] = [
                    'estado_habilitacion' => $row->estado_habilitacion,
                    'observacion' => $row->observacion,
                ];
            }

            usort(
                $noHabilitados,
                static fn (array $a, array $b): int => strcmp($a['codigo_sis'], $b['codigo_sis'])
            );

            $this->applyRows($examId, $targets, $groupIds);

            $this->registerAudit(
                $examId,
                $teacherId,
                'ARCHIVO',
                $before,
                $targets,
                ['habilitados' => $habilitados, 'no_habilitados' => count($noHabilitados)]
            );

            return new EligibleListImportResult(
                $analysis->totalRows(),
                $analysis->inconsistentRows(),
                $noEncontrados,
                $habilitados,
                $noHabilitados
            );
        });

        try {
            $this->previewStore->forget($token);
        } catch (Throwable $exception) {
            Log::warning('No se pudo descartar el preview de habilitados tras confirmarlo', [
                'id_examen' => $examId,
                'motivo' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Aplica cambios puntuales en una transacción.
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
            $this->access->lockForWrite($examId, $teacherId);

            $requestedIds = array_values(array_unique(array_map(
                static fn (array $c): int => (int) $c['id_estudiante'],
                $cambios
            )));

            // La nómina vive en grupo_estudiante ⋈ grupo_examen: un estudiante sin fila en
            // examen_estudiante también pertenece al examen (aparece como HABILITADO).
            $existing = $this->roster->expected($examId)
                ->whereIn('estudiante.id_estudiante', $requestedIds)
                ->get([
                    'estudiante.id_estudiante',
                    'estado_habilitacion',
                    'observacion',
                    'id_grupo',
                ])
                ->keyBy('id_estudiante');

            if (count($requestedIds) !== $existing->count()) {
                throw ValidationException::withMessages([
                    'cambios' => ['Uno o más estudiantes no pertenecen al examen indicado.'],
                ]);
            }

            $hasSpecialCase = $existing->contains(
                static fn ($row): bool => $row->estado_habilitacion === self::CASO_ESPECIAL
            );

            if ($hasSpecialCase) {
                throw ValidationException::withMessages([
                    'cambios' => ['No se puede modificar a un estudiante en CASO ESPECIAL desde esta vista.'],
                ]);
            }

            $targets = [];
            $groupIds = [];
            $before = [];

            foreach ($cambios as $cambio) {
                $id = (int) $cambio['id_estudiante'];
                $row = $existing[$id];

                $targets[$id] = (string) $cambio['estado_habilitacion'] === self::HABILITADO
                    ? ['estado_habilitacion' => self::HABILITADO, 'observacion' => null]
                    : [
                        'estado_habilitacion' => self::NO_HABILITADO,
                        'observacion' => MotivoNoHabilitacion::toObservation(
                            (string) ($cambio['motivo'] ?? ''),
                            isset($cambio['detalle']) ? (string) $cambio['detalle'] : null
                        ),
                    ];
                $groupIds[$id] = (int) $row->id_grupo;
                $before[$id] = [
                    'estado_habilitacion' => $row->estado_habilitacion,
                    'observacion' => $row->observacion,
                ];
            }

            $this->applyRows($examId, $targets, $groupIds);

            $this->registerAudit($examId, $teacherId, 'MANUAL', $before, $targets, []);
        });
    }

    public function current(int $examId, string $teacherId): EligibleListCurrentResult
    {
        $exam = $this->access->getAvailableForRead($examId, $teacherId);

        $editable = $this->access->isWritable($exam);

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
     * Exámenes del docente para el selector de HU-028: solo los que siguen dentro de la ventana de
     * escritura (PROGRAMADO con el control de ingreso sin abrir). Sin filtro de período activo: un
     * examen de un período anterior sigue siendo válido mientras no abra el control. Sin paginación:
     * el docente tiene pocos exámenes en ese estado. Ordenados por fecha ascendente, luego por hora
     * de inicio y por id.
     *
     * @return Collection<int, Exam>
     */
    public function examsForSelector(string $teacherId): Collection
    {
        return $this->access->applyWritableWindow(
            Exam::query()
                ->with(['subject', 'career', 'groups'])
                ->where('id_usuario_docente', $teacherId)
        )
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->get();
    }

    /**
     * Escribe cada fila por ensureRow() (que no pisa estado_ingreso, hora_ingreso ni id_ambiente)
     * y luego fija su estado de habilitación y su observación.
     *
     * @param array<int, array{estado_habilitacion: string, observacion: ?string}> $targets por id de estudiante
     * @param array<int, int> $groupIds grupo de la nómina por id de estudiante
     */
    private function applyRows(int $examId, array $targets, array $groupIds): void
    {
        foreach ($targets as $studentId => $target) {
            $this->roster->ensureRow($examId, $studentId, $groupIds[$studentId]);

            DB::table('examen_estudiante')
                ->where('id_examen', $examId)
                ->where('id_estudiante', $studentId)
                ->update([
                    'estado_habilitacion' => $target['estado_habilitacion'],
                    'observacion' => $target['observacion'],
                ]);
        }
    }

    /**
     * @param array<int, array{estado_habilitacion: string, observacion: ?string}> $before
     * @param array<int, array{estado_habilitacion: string, observacion: ?string}> $after
     * @param array<string, int> $counts
     */
    private function registerAudit(
        int $examId,
        string $teacherId,
        string $origin,
        array $before,
        array $after,
        array $counts
    ): void {
        $this->auditLog->registrar(
            'GUARDAR_HABILITADOS',
            'examen_estudiante',
            [
                'id_examen' => $examId,
                'origen' => $origin,
                'cambios' => $before,
            ],
            [
                'id_examen' => $examId,
                'origen' => $origin,
                'total_cambios' => count($after),
                'cambios' => $after,
            ] + $counts,
            $teacherId
        );
    }

    private function findPreviewFor(string $token, int $examId, string $teacherId): EligibleListPreview
    {
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

        return $preview;
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
