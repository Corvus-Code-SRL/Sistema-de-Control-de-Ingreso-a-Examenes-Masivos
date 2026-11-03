<?php

namespace App\Services\Security;

use App\Models\Action;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogChanges;
use App\Support\Audit\AuditLogSanitizer;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\SystemAccounts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Consulta de solo lectura de la bitácora (HU-015). No escribe en `log` ni audita la propia consulta.
 *
 * Devuelve filas ya presentadas (arreglos): el usuario, la acción y el módulo con su etiqueta, el registro
 * afectado y, en el detalle, la lista de cambios. Los valores crudos antiguo_valor/nuevo_valor no salen de
 * aquí; los campos sensibles los oculta AuditLogSanitizer.
 */
class AuditLogQueryService
{
    public const OTHERS = 'otros';

    public const MAX_PAGE = 100000;

    private const RELATIONS = [
        'accion:id_accion,operacion',
        'usuario:id_usuario,nombre,apellido_paterno,apellido_materno,cod_sis',
    ];

    private const FULL_NAME = "CONCAT_WS(' ', usuario.nombre, usuario.apellido_paterno, usuario.apellido_materno)";

    /** Palabras de la búsqueda de usuario que se consideran; el resto se ignora para acotar la consulta. */
    private const MAX_TERMS = 5;

    private const MAX_REFERENCES = 5;

    private AuditLogSanitizer $sanitizer;

    private AuditLogChanges $changes;

    public function __construct(AuditLogSanitizer $sanitizer, AuditLogChanges $changes)
    {
        $this->sanitizer = $sanitizer;
        $this->changes = $changes;
    }

    /**
     * Operaciones de la más reciente a la más antigua (a igual instante, la de mayor id).
     *
     * Filtros opcionales: q (nombre o código SIS del usuario), accion (código), modulo (clave o «otros»),
     * desde/hasta (fechas de La Paz, ambas incluidas) y page.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with(self::RELATIONS)
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_log');

        $this->filterByUser($query, $filters['q'] ?? null);
        $this->filterByAction($query, $filters['accion'] ?? null);
        $this->filterByModule($query, $filters['modulo'] ?? null);
        $this->filterByRange($query, $filters['desde'] ?? null, $filters['hasta'] ?? null);

        $page = max(1, min(self::MAX_PAGE, (int) ($filters['page'] ?? 1)));
        $logs = $query->paginate((int) config('sciem.bitacora.por_pagina', 50), ['*'], 'page', $page);

        $people = $this->peopleFor($logs->getCollection()->all());

        return $logs->through(fn (AuditLog $log) => $this->present($log, $people));
    }

    /**
     * Una operación con sus cambios campo por campo.
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException si no existe
     */
    public function find(int $id): array
    {
        $log = $id < 1
            ? null
            : AuditLog::query()
                ->with(self::RELATIONS)
                ->find($id);

        if ($log === null) {
            throw new ModelNotFoundException('No existe la operación indicada.');
        }

        $entry = $this->present($log, $this->peopleFor([$log]));
        $entry['cambios'] = $this->changes->between($log->antiguo_valor, $log->nuevo_valor);
        $entry['docente_examen'] = $entry['automatico'] ? $this->personOf($log->usuario) : null;

        return $entry;
    }

    /**
     * Valores de los selectores de acción y módulo.
     *
     * @return array{acciones: array<int, array<string, string>>, modulos: array<int, array<string, string>>}
     */
    public function options(): array
    {
        $actions = Action::query()->orderBy('operacion')->pluck('operacion')
            ->map(fn ($code) => ['codigo' => (string) $code, 'etiqueta' => $this->actionLabel((string) $code)])
            ->sortBy(fn (array $action) => Str::lower(Str::ascii($action['etiqueta'])))
            ->values()
            ->all();

        $modules = [];
        foreach ((array) config('sciem.bitacora.modulos', []) as $key => $label) {
            $modules[] = ['clave' => (string) $key, 'etiqueta' => (string) $label];
        }
        $modules[] = ['clave' => self::OTHERS, 'etiqueta' => 'Otros'];

        return ['acciones' => $actions, 'modulos' => $modules];
    }

    private function filterByUser(Builder $query, ?string $search): void
    {
        $normalized = AccentInsensitiveSearch::normalize((string) $search);
        $terms = array_slice(preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_TERMS);

        if ($terms === []) {
            return;
        }

        $query->whereHas('usuario', function (Builder $user) use ($terms) {
            foreach ($terms as $term) {
                $pattern = AccentInsensitiveSearch::pattern($term);

                $user->where(function (Builder $match) use ($pattern) {
                    $match->whereRaw(AccentInsensitiveSearch::condition(self::FULL_NAME), [$pattern])
                        ->orWhereRaw(AccentInsensitiveSearch::condition('usuario.cod_sis'), [$pattern]);
                });
            }
        });
    }

    private function filterByAction(Builder $query, ?string $code): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $query->whereIn('id_accion', Action::query()->where('operacion', $code)->select('id_accion'));
    }

    private function filterByModule(Builder $query, ?string $module): void
    {
        if ($module === null || $module === '') {
            return;
        }

        if ($module === self::OTHERS) {
            $query->whereNotIn('tabla_afectada', array_keys((array) config('sciem.bitacora.tablas', [])));

            return;
        }

        $query->whereIn('tabla_afectada', $this->tablesOf($module));
    }

    /**
     * Intervalo semiabierto [desde 00:00, hasta + 1 día 00:00) en hora de La Paz. Los límites viajan en UTC con
     * desfase explícito, así el resultado no depende de la zona horaria de la sesión de PostgreSQL.
     */
    private function filterByRange(Builder $query, ?string $from, ?string $to): void
    {
        $zone = (string) config('sciem.zona_horaria', 'America/La_Paz');

        if ($from !== null && $from !== '') {
            $query->where('fecha_hora', '>=', $this->boundary(Carbon::parse($from, $zone)));
        }

        if ($to !== null && $to !== '') {
            $query->where('fecha_hora', '<', $this->boundary(Carbon::parse($to, $zone)->addDay()));
        }
    }

    private function boundary(Carbon $day): string
    {
        return $day->startOfDay()->utc()->format('Y-m-d H:i:sP');
    }

    /** @return array<int, string> */
    private function tablesOf(string $module): array
    {
        $tables = [];

        foreach ((array) config('sciem.bitacora.tablas', []) as $table => $meta) {
            if (($meta['modulo'] ?? null) === $module) {
                $tables[] = (string) $table;
            }
        }

        return $tables;
    }

    /**
     * Cuentas a las que se refiere el registro afectado (tablas «persona»), en una sola consulta.
     *
     * @param  array<int, AuditLog>  $logs
     * @return array<string, User>
     */
    private function peopleFor(array $logs): array
    {
        $ids = [];

        foreach ($logs as $log) {
            $id = $this->personIdOf($log);

            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id_usuario', array_keys($ids))
            ->get(['id_usuario', 'nombre', 'apellido_paterno', 'apellido_materno', 'cod_sis'])
            ->keyBy('id_usuario')
            ->all();
    }

    /** La cuenta del registro: la que indica el dato o, si no la hay (p. ej. cambio de contraseña), quien actuó. */
    private function personIdOf(AuditLog $log): ?string
    {
        $meta = $this->tableMeta($log->tabla_afectada);

        if (empty($meta['persona'])) {
            return null;
        }

        $data = $this->dataOf($log);
        $id = $data['id_usuario'] ?? $log->id_usuario;

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    /**
     * @param  array<string, User>  $people
     * @return array<string, mixed>
     */
    private function present(AuditLog $log, array $people): array
    {
        $table = (string) $log->tabla_afectada;
        $meta = $this->tableMeta($table);
        $actor = $log->usuario;
        $kind = $this->actorKind($log);

        $code = $log->accion === null ? null : (string) $log->accion->operacion;
        $moduleKey = $meta['modulo'] ?? self::OTHERS;
        $ids = $this->recordIds($meta, $this->dataOf($log));
        $personId = $this->personIdOf($log);
        $person = $personId === null ? null : ($people[$personId] ?? null);

        return [
            'id_log' => (int) $log->id_log,
            'fecha_hora' => $log->fecha_hora->copy()->utc()->toIso8601String(),
            'usuario' => [
                'id_usuario' => (string) $log->id_usuario,
                'nombre_completo' => $actor === null ? null : $actor->nombre_completo,
                'cod_sis' => $actor === null ? null : $actor->cod_sis,
                'tipo' => $kind,
                'etiqueta' => $this->actorLabel($kind, $actor),
            ],
            'accion' => [
                'codigo' => $code,
                'etiqueta' => $code === null ? 'Sin acción' : $this->actionLabel($code),
            ],
            'modulo' => [
                'clave' => $moduleKey,
                'etiqueta' => $moduleKey === self::OTHERS
                    ? 'Otros'
                    : (string) config('sciem.bitacora.modulos.' . $moduleKey, $moduleKey),
            ],
            'registro' => [
                'tabla' => $table,
                'etiqueta' => (string) ($meta['etiqueta'] ?? $table),
                'descripcion' => $this->recordDescription($meta, $table, $ids, $person),
                'ids' => $ids,
            ],
            'automatico' => $kind === 'automatico',
        ];
    }

    /**
     * Quién figura como autor. Las cuentas de sistema firman seeders y comandos; el inicio y el cierre
     * automáticos de un examen se firman con el docente dueño, y solo los marca el dato guardado.
     */
    private function actorKind(AuditLog $log): string
    {
        $actor = $log->usuario;

        if ($actor !== null && SystemAccounts::isSystemCode((string) $actor->cod_sis)) {
            return 'sistema';
        }

        $after = $log->nuevo_valor;

        if (is_array($after) && (
            ($after['tipo_inicio'] ?? null) === 'AUTOMATICO'
            || ($after['tipo_finalizacion'] ?? null) === 'AUTOMATICA'
        )) {
            return 'automatico';
        }

        return 'persona';
    }

    private function actorLabel(string $kind, ?User $actor): string
    {
        if ($kind === 'sistema') {
            return 'Sistema';
        }

        if ($kind === 'automatico') {
            return 'Automático';
        }

        return $actor === null ? 'Cuenta desconocida' : $actor->nombre_completo;
    }

    /** @return array{nombre_completo: string, cod_sis: string}|null */
    private function personOf(?User $user): ?array
    {
        return $user === null ? null : ['nombre_completo' => $user->nombre_completo, 'cod_sis' => $user->cod_sis];
    }

    private function actionLabel(string $code): string
    {
        return (string) config('sciem.bitacora.etiquetas_accion.' . $code, $code);
    }

    /** @return array<string, mixed> */
    private function tableMeta(?string $table): array
    {
        $meta = $table === null ? null : config('sciem.bitacora.tablas.' . $table);

        return is_array($meta) ? $meta : [];
    }

    /** @return array<string, mixed> Los valores de la operación; el «después» manda sobre el «antes». */
    private function dataOf(AuditLog $log): array
    {
        $data = $log->nuevo_valor ?: $log->antiguo_valor;

        return is_array($data) ? $data : [];
    }

    /**
     * Claves que identifican el registro: las de la tabla en config o, si no está mapeada, los primeros `id_*`.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recordIds(array $meta, array $data): array
    {
        $keys = $meta['claves'] ?? null;

        if ($keys === null) {
            $keys = array_slice(array_values(array_filter(
                array_keys($data),
                fn ($key) => is_string($key) && strpos($key, 'id_') === 0 && is_scalar($data[$key])
            )), 0, 3);
        }

        $ids = [];

        foreach ($keys as $key) {
            if (! isset($data[$key]) || $this->sanitizer->isHidden((string) $key, $data[$key])) {
                continue;
            }

            if (is_scalar($data[$key]) || is_array($data[$key])) {
                $ids[$key] = $this->sanitizer->redact($data[$key]);
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $ids
     */
    private function recordDescription(array $meta, string $table, array $ids, ?User $person): string
    {
        $label = (string) ($meta['etiqueta'] ?? $table);

        if (! empty($meta['persona']) && $person !== null) {
            return $label . ': ' . $person->nombre_completo . ' (' . $person->cod_sis . ')';
        }

        $parts = [];

        foreach ($ids as $value) {
            $values = is_array($value) ? array_slice($value, 0, self::MAX_REFERENCES) : [$value];
            $parts[] = implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '…', $values));
        }

        return $parts === [] ? $label : $label . ' #' . implode(' / ', $parts);
    }
}
