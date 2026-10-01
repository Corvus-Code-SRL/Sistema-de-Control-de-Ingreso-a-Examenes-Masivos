<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Services\Exams\ExamTimingService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Estado operativo del control de ingreso, preparado en Redis antes de abrirlo. */
class EntryControlSnapshotService
{
    private const PREFIX = 'entry-control';

    private ExamTimingService $timing;

    public function __construct(ExamTimingService $timing)
    {
        $this->timing = $timing;
    }

    public function stage(Exam $exam): string
    {
        $examId = (int) $exam->id_examen;
        $token = (string) Str::uuid();
        $expiresAt = $this->expirationFor($exam);

        $rooms = DB::table('examen_ambiente as ea')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
            ->where('ea.id_examen', $examId)
            ->orderBy('ea.id_ambiente')
            ->get(['ea.id_ambiente', 'a.nro_aula', 'a.capacidad'])
            ->map(fn ($room) => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
                'capacidad' => (int) $room->capacidad,
            ])->all();

        $memberships = DB::table('grupo_estudiante as gs')
            ->join('grupo_examen as gx', 'gx.id_grupo', '=', 'gs.id_grupo')
            ->join('grupo as g', 'g.id_grupo', '=', 'gs.id_grupo')
            ->where('gx.id_examen', $examId)
            ->orderBy('gs.id_estudiante')
            ->orderBy('gs.id_grupo')
            ->get(['gs.id_estudiante', 'gs.id_grupo', 'g.num_grupo']);

        $membersByStudent = [];
        foreach ($memberships as $membership) {
            $studentId = (int) $membership->id_estudiante;
            if (! isset($membersByStudent[$studentId])) {
                $membersByStudent[$studentId] = [
                    'id_grupo' => (int) $membership->id_grupo,
                    'num_grupo' => $membership->num_grupo,
                ];
            }
        }

        $assignedRooms = $this->assignRooms(array_keys($membersByStudent), $rooms);
        $entries = $this->entries($examId);
        $risks = $this->risks(array_keys($membersByStudent));
        $career = DB::table('carrera')->where('id_carrera', $exam->id_carrera)->value('nombre');

        $controllers = [
            (string) $exam->id_usuario_docente => [
                'rol' => 'DOCENTE',
                'ambientes' => array_column($rooms, 'id_ambiente'),
            ],
        ];
        $auxiliaries = DB::table('examen_auxiliar as ea')
            ->join('usuario as u', 'u.id_usuario', '=', 'ea.id_usuario')
            ->where('ea.id_examen', $examId)
            ->whereNotNull('ea.id_ambiente')
            ->where('u.estado', User::ESTADO_ACTIVO)
            ->get(['ea.id_usuario', 'ea.id_ambiente']);
        foreach ($auxiliaries as $auxiliary) {
            $controllers[(string) $auxiliary->id_usuario] = [
                'rol' => 'AUXILIAR',
                'ambientes' => [(int) $auxiliary->id_ambiente],
            ];
        }

        $meta = [
            'id_examen' => $examId,
            'estado' => Exam::EN_INGRESO,
            'carrera' => $career,
            'ambientes' => $rooms,
            'controladores' => $controllers,
            'control_cierra_en' => $this->timing->endsAt($exam)->toIso8601String(),
            'expira_en' => $expiresAt->toIso8601String(),
        ];

        $students = DB::table('estudiante')->orderBy('id_estudiante')->get();
        $search = [];
        $values = [$this->key($examId, $token, 'meta') => $meta];

        foreach ($students as $student) {
            $studentId = (int) $student->id_estudiante;
            $group = $membersByStudent[$studentId] ?? null;
            $entry = $entries[$studentId] ?? null;
            $fullName = trim(
                $student->nombre . ' ' . $student->apellido_paterno . ' ' . $student->apellido_materno
            );
            $payload = [
                'estudiante' => [
                    'id_estudiante' => $studentId,
                    'cod_sis' => $student->cod_sis,
                    'ci' => $student->ci,
                    'ci_pendiente' => $student->ci === null,
                    'nombre_completo' => $fullName,
                    'carrera' => $career,
                    'foto_url' => null,
                ],
                'nombre' => $student->nombre,
                'apellido_paterno' => $student->apellido_paterno,
                'apellido_materno' => $student->apellido_materno,
                'grupo' => $group,
                'estado_habilitacion' => $entry['estado_habilitacion'] ?? null,
                'ambiente_asignado' => $assignedRooms[$studentId] ?? null,
                'antecedentes' => $risks[$studentId] ?? $this->emptyRisk(),
                'ingreso_previo' => $entry['ingreso_previo'] ?? null,
            ];

            $values[$this->key($examId, $token, 'student', $studentId)] = $payload;
            $values[$this->key($examId, $token, 'sis', sha1((string) $student->cod_sis))] = $studentId;

            if ($group !== null) {
                $search[] = [
                    'id_estudiante' => $studentId,
                    'cod_sis' => $student->cod_sis,
                    'nombre_completo' => $fullName,
                    'nombre_busqueda' => mb_strtolower($fullName),
                ];
            }
        }

        usort($search, fn (array $left, array $right) => strcmp(
            $left['nombre_busqueda'],
            $right['nombre_busqueda']
        ));
        $values[$this->key($examId, $token, 'search')] = $search;
        $values[$this->key($examId, $token, 'status')] = $this->initialStatus(
            count($membersByStudent),
            $entries
        );

        foreach (array_chunk($values, 500, true) as $chunk) {
            if (! $this->cache()->putMany($chunk, $expiresAt)) {
                throw new \RuntimeException('No se pudo escribir el snapshot del examen en Redis.');
            }
        }

        return $token;
    }

    public function activate(int $examId, string $token): void
    {
        $meta = $this->cache()->get($this->key($examId, $token, 'meta'));
        if (! is_array($meta)) {
            throw new \RuntimeException('No se pudo preparar el estado operativo del examen.');
        }

        if (! $this->cache()->put(
            $this->activeKey($examId),
            $token,
            Carbon::parse($meta['expira_en'])
        )) {
            throw new \RuntimeException('No se pudo activar el snapshot del examen.');
        }
    }

    public function ready(int $examId): bool
    {
        $token = $this->activeToken($examId);
        if ($token === null) {
            return false;
        }

        $meta = $this->cache()->get($this->key($examId, $token, 'meta'));

        return is_array($meta)
            && isset($meta['control_cierra_en'])
            && Carbon::now(config('sciem.zona_horaria'))->lessThan(Carbon::parse($meta['control_cierra_en']));
    }

    public function deactivate(int $examId): void
    {
        $this->cache()->forget($this->activeKey($examId));
    }

    public function roomFor(int $examId, User $actor, int $requestedRoomId): int
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }

        $meta = $this->meta($examId);
        $controller = $meta['controladores'][(string) $actor->id_usuario] ?? null;
        if ($controller === null) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }

        if (! in_array($requestedRoomId, $controller['ambientes'], true)) {
            throw new AuthorizationException(
                $controller['rol'] === 'DOCENTE'
                    ? 'El ambiente no pertenece a este examen.'
                    : 'No está habilitado para controlar en este ambiente.'
            );
        }

        return $requestedRoomId;
    }

    public function assertCanControl(int $examId, User $actor): void
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }

        $controllers = $this->meta($examId)['controladores'];
        if (! isset($controllers[(string) $actor->id_usuario])) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }
    }

    public function studentBySis(int $examId, string $sis): ?array
    {
        $token = $this->activeTokenOrFail($examId);
        $studentId = $this->cache()->get($this->key($examId, $token, 'sis', sha1($sis)));

        return $studentId === null ? null : $this->studentById($examId, (int) $studentId, $token);
    }

    public function studentById(int $examId, int $studentId, ?string $token = null): ?array
    {
        $token = $token ?? $this->activeTokenOrFail($examId);
        $student = $this->cache()->get($this->key($examId, $token, 'student', $studentId));

        return is_array($student) ? $student : null;
    }

    public function search(int $examId, User $actor, string $name): array
    {
        $this->assertCanControl($examId, $actor);
        $token = $this->activeTokenOrFail($examId);
        $needle = mb_strtolower(trim($name));
        $matches = [];

        foreach ($this->cache()->get($this->key($examId, $token, 'search'), []) as $student) {
            if (mb_strpos($student['nombre_busqueda'], $needle) === false) {
                continue;
            }
            unset($student['nombre_busqueda']);
            $matches[] = $student;
            if (count($matches) === 10) {
                break;
            }
        }

        return $matches;
    }

    public function assignedRoomFor(int $examId, int $studentId): ?object
    {
        $student = $this->studentById($examId, $studentId);
        $room = $student['ambiente_asignado'] ?? null;

        return $room === null ? null : (object) $room;
    }

    public function currentStatus(int $examId, User $actor, ?string $clientVersion): array
    {
        $this->assertCanControl($examId, $actor);
        $token = $this->activeTokenOrFail($examId);
        $status = $this->cache()->get($this->key($examId, $token, 'status'));
        if (! is_array($status)) {
            throw new \RuntimeException('El estado operativo del examen no está disponible.');
        }

        if ($clientVersion !== null && $clientVersion === $status['version']) {
            return ['sin_cambios' => true, 'version' => $status['version']];
        }

        return $status;
    }

    public function markEntered(
        int $examId,
        int $studentId,
        int $roomId,
        User $actor,
        string $registeredAt,
        string $entryTime
    ): void {
        $token = $this->activeTokenOrFail($examId);

        $this->cache()->lock($this->key($examId, $token, 'update-lock'), 5)->block(3, function () use (
            $examId, $token, $studentId, $roomId, $actor, $registeredAt, $entryTime
        ): void {
            $meta = $this->cache()->get($this->key($examId, $token, 'meta'));
            $student = $this->studentById($examId, $studentId, $token);
            $status = $this->cache()->get($this->key($examId, $token, 'status'));
            if (! is_array($meta) || $student === null || ! is_array($status)) {
                throw new \RuntimeException('El estado operativo del examen está incompleto.');
            }

            $room = collect($meta['ambientes'])->firstWhere('id_ambiente', $roomId);
            $controllerName = trim($actor->nombre . ' ' . $actor->apellido_paterno);
            $student['ingreso_previo'] = [
                'registrado_en' => $registeredAt,
                'hora_ingreso' => $entryTime,
                'id_ambiente' => $roomId,
                'nro_aula' => $room['nro_aula'] ?? null,
                'controlador' => $controllerName,
            ];

            $recent = [
                'id_estudiante' => $studentId,
                'cod_sis' => $student['estudiante']['cod_sis'],
                'nombre' => $student['nombre'],
                'apellido_paterno' => $student['apellido_paterno'],
                'apellido_materno' => $student['apellido_materno'],
                'hora_ingreso' => $entryTime,
                'registrado_en' => $registeredAt,
                'nro_aula' => $room['nro_aula'] ?? null,
                'controlador_nombre' => $actor->nombre,
                'controlador_apellido' => $actor->apellido_paterno,
            ];

            $status['ingresados']++;
            $status['pendientes'] = max(0, $status['pendientes'] - 1);
            array_unshift($status['ultimos_ingresos'], $recent);
            $status['ultimos_ingresos'] = array_slice($status['ultimos_ingresos'], 0, 10);
            $status['version'] = (string) Str::uuid();
            $expiresAt = Carbon::parse($meta['expira_en']);

            if (! $this->cache()->putMany([
                $this->key($examId, $token, 'student', $studentId) => $student,
                $this->key($examId, $token, 'status') => $status,
            ], $expiresAt)) {
                throw new \RuntimeException('No se pudo actualizar el estado del ingreso en Redis.');
            }
        });
    }

    private function meta(int $examId): array
    {
        $token = $this->activeTokenOrFail($examId);
        $meta = $this->cache()->get($this->key($examId, $token, 'meta'));
        if (! is_array($meta) || $meta['estado'] !== Exam::EN_INGRESO) {
            abort(409, 'El control de ingreso del examen no está abierto.');
        }

        if (! isset($meta['control_cierra_en'])
            || Carbon::now(config('sciem.zona_horaria'))->greaterThanOrEqualTo(
                Carbon::parse($meta['control_cierra_en'])
            )) {
            abort(409, 'El tiempo de control de ingreso del examen ya terminó.');
        }

        return $meta;
    }

    private function activeTokenOrFail(int $examId): string
    {
        $token = $this->activeToken($examId);
        if ($token === null) {
            abort(409, 'El control de ingreso del examen no está abierto.');
        }

        return $token;
    }

    private function activeToken(int $examId): ?string
    {
        $token = $this->cache()->get($this->activeKey($examId));
        return is_string($token) && $token !== '' ? $token : null;
    }

    private function initialStatus(int $total, array $entries): array
    {
        $entered = collect($entries)
            ->filter(fn (array $entry) => $entry['ingreso_previo'] !== null)
            ->sortByDesc(fn (array $entry) => $entry['ingreso_previo']['registrado_en'] ?? '')
            ->values();

        return [
            'ingresados' => $entered->count(),
            'pendientes' => max(0, $total - $entered->count()),
            'total' => $total,
            'ultimos_ingresos' => $entered->take(10)->pluck('reciente')->values()->all(),
            'version' => (string) Str::uuid(),
        ];
    }

    private function entries(int $examId): array
    {
        return DB::table('examen_estudiante as ee')
            ->join('estudiante as est', 'est.id_estudiante', '=', 'ee.id_estudiante')
            ->leftJoin('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'ee.id_usuario_controlador')
            ->where('ee.id_examen', $examId)
            ->get([
                'ee.id_estudiante', 'ee.estado_habilitacion', 'ee.estado_ingreso',
                'ee.hora_ingreso', 'ee.registrado_en', 'ee.id_ambiente', 'a.nro_aula',
                'est.cod_sis', 'est.nombre', 'est.apellido_paterno', 'est.apellido_materno',
                'u.nombre as controlador_nombre', 'u.apellido_paterno as controlador_apellido',
            ])->mapWithKeys(function ($entry): array {
                $realEntry = in_array($entry->estado_ingreso, ['INGRESO', 'CON_RETRASO'], true);
                $previous = $realEntry ? [
                    'registrado_en' => $entry->registrado_en,
                    'hora_ingreso' => $entry->hora_ingreso,
                    'id_ambiente' => $entry->id_ambiente === null ? null : (int) $entry->id_ambiente,
                    'nro_aula' => $entry->nro_aula,
                    'controlador' => trim(
                        ($entry->controlador_nombre ?? '') . ' ' . ($entry->controlador_apellido ?? '')
                    ),
                ] : null;

                return [(int) $entry->id_estudiante => [
                    'estado_habilitacion' => $entry->estado_habilitacion,
                    'ingreso_previo' => $previous,
                    'reciente' => $realEntry ? [
                        'id_estudiante' => (int) $entry->id_estudiante,
                        'cod_sis' => $entry->cod_sis,
                        'nombre' => $entry->nombre,
                        'apellido_paterno' => $entry->apellido_paterno,
                        'apellido_materno' => $entry->apellido_materno,
                        'hora_ingreso' => $entry->hora_ingreso,
                        'registrado_en' => $entry->registrado_en,
                        'nro_aula' => $entry->nro_aula,
                        'controlador_nombre' => $entry->controlador_nombre,
                        'controlador_apellido' => $entry->controlador_apellido,
                    ] : null,
                ]];
            })->all();
    }

    private function risks(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::table('central_riesgo as cr')
            ->join('reporte_estudiante as re', 're.id_reporte_est', '=', 'cr.id_reporte_est')
            ->join('tipo_falta as tf', 'tf.id_falta', '=', 're.id_tipo_falta')
            ->whereIn('cr.id_estudiante', $studentIds)
            ->orderBy('cr.id_estudiante')
            ->orderByDesc('cr.fecha_registro')
            ->get(['cr.id_estudiante', 'tf.nombre']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->id_estudiante][] = $row->nombre;
        }

        return collect($grouped)->map(function (array $names): array {
            return [
                'tiene_antecedentes' => true,
                'cantidad' => count($names),
                'resumen' => collect(array_slice($names, 0, 3))->unique()->implode(', '),
            ];
        })->all();
    }

    private function assignRooms(array $studentIds, array $rooms): array
    {
        sort($studentIds, SORT_NUMERIC);
        $assigned = [];
        $roomIndex = 0;
        $limit = $rooms[0]['capacidad'] ?? 0;

        foreach (array_values($studentIds) as $position => $studentId) {
            while ($position + 1 > $limit && isset($rooms[$roomIndex + 1])) {
                $roomIndex++;
                $limit += $rooms[$roomIndex]['capacidad'];
            }
            if (isset($rooms[$roomIndex]) && $position + 1 <= $limit) {
                $assigned[$studentId] = [
                    'id_ambiente' => $rooms[$roomIndex]['id_ambiente'],
                    'nro_aula' => $rooms[$roomIndex]['nro_aula'],
                ];
            }
        }

        return $assigned;
    }

    private function emptyRisk(): array
    {
        return ['tiene_antecedentes' => false, 'cantidad' => 0, 'resumen' => null];
    }

    private function expirationFor(Exam $exam): Carbon
    {
        $timezone = config('sciem.zona_horaria');
        $expiresAt = $this->timing->finishesAutomaticallyAt($exam);

        return $expiresAt->isFuture() ? $expiresAt : Carbon::now($timezone)->addHours(2);
    }

    private function cache(): Repository
    {
        return Cache::store(config('sciem.entry_control_cache_store', 'redis'));
    }

    private function activeKey(int $examId): string
    {
        return self::PREFIX . ":{$examId}:active";
    }

    private function key(int $examId, string $token, string ...$segments): string
    {
        return self::PREFIX . ":{$examId}:{$token}:" . implode(':', $segments);
    }
}
