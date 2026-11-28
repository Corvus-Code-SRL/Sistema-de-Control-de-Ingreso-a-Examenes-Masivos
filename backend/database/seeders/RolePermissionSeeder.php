<?php

namespace Database\Seeders;

use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use App\Support\SystemActor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lleva a permiso, permiso_rol, ui y ui_permiso lo que dice config/sciem.php, y guarda la historia (RNF-01).
 *
 * config/sciem.php sigue siendo quien decide el acceso (UserRoleService::canUse); estas tablas son el
 * registro de qué permisos tuvo cada rol, desde/hasta cuándo, y qué pantallas habilita cada permiso:
 *   - cada función de `sciem.access` y cada clave de `sciem.permisos_propios` es un permiso;
 *   - un par permiso–rol nuevo abre una fila de permiso_rol (fecha_inicio = ahora);
 *   - un par que ya no está cierra su fila (fecha_fin = ahora); nunca se borra, así lo que el rol hizo
 *     mientras tenía el permiso sigue constando como válido;
 *   - cada pantalla de `sciem.capacidades` y de los permisos propios es una fila de ui, y su relación con
 *     el permiso queda ACTIVA o INACTIVA en ui_permiso.
 *
 * Idempotente: el deploy lo corre en cada `up` (db:seed --force) y sin cambios en config no escribe nada.
 * Cada apertura, cierre o cambio queda en la bitácora firmado por la cuenta de sistema. Requiere
 * UserSeeder, RoleSeeder y ActionSeeder.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $actorId = app(SystemActor::class)->id();

        if ($actorId === null || ! DB::table('usuario')->where('id_usuario', $actorId)->exists()) {
            throw new LogicException(
                'RolePermissionSeeder necesita la cuenta de sistema (ejecute antes UserSeeder).'
            );
        }

        $audit = app(AuditLogService::class);

        DB::transaction(function () use ($actorId, $audit) {
            $now = now();
            $roles = DB::table('rol')->pluck('id_rol', 'nombre_rol')->all();
            $definitions = $this->definitions();

            $permissionIds = [];
            foreach ($definitions as $name => $definition) {
                $permissionIds[$name] = $this->permissionId($name, $definition['descripcion'], $actorId, $audit);
            }

            $wanted = $this->wantedPairs($definitions, $permissionIds, $roles);
            $this->syncRolePermissions($wanted, $now, $actorId, $audit);
            $this->syncScreens($definitions, $permissionIds, $actorId, $audit);
        });
    }

    /**
     * Cada permiso con sus roles, sus pantallas y su descripción, leídos de la configuración.
     *
     * @return array<string, array{roles: array<int, string>, pantallas: array<int, string>,
     *     descripcion: string}>
     */
    private function definitions(): array
    {
        $screens = (array) config('sciem.capacidades', []);
        $definitions = [];

        foreach (array_keys($screens) as $feature) {
            if (! array_key_exists($feature, (array) config('sciem.access', []))) {
                throw new LogicException(
                    "sciem.capacidades.{$feature} no corresponde a ninguna función de sciem.access."
                );
            }
        }

        foreach ((array) config('sciem.access', []) as $feature => $roleNames) {
            $definitions[(string) $feature] = [
                'roles' => array_values(array_map('strval', (array) $roleNames)),
                'pantallas' => array_values(array_map('strval', (array) ($screens[$feature] ?? []))),
                'descripcion' => 'Acceso a la función «' . $feature . '» (config/sciem.php, sciem.access).',
            ];
        }

        foreach ((array) config('sciem.permisos_propios', []) as $name => $own) {
            $definitions[(string) $name] = [
                'roles' => [(string) $own['rol']],
                'pantallas' => array_values(array_map('strval', (array) ($own['capacidades'] ?? []))),
                'descripcion' => 'Permiso propio del rol ' . $own['rol']
                    . ' (config/sciem.php, sciem.permisos_propios).',
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<string, array{roles: array<int, string>, pantallas: array<int, string>,
     *     descripcion: string}>  $definitions
     * @param  array<string, int>  $permissionIds
     * @param  array<string, int>  $roles
     * @return array<string, array{id_permiso: int, id_rol: int, permiso: string, rol: string}>
     */
    private function wantedPairs(array $definitions, array $permissionIds, array $roles): array
    {
        $pairs = [];

        foreach ($definitions as $name => $definition) {
            foreach ($definition['roles'] as $roleName) {
                if (! isset($roles[$roleName])) {
                    throw new LogicException(
                        "El rol «{$roleName}» del permiso «{$name}» no existe (ejecute RoleSeeder)."
                    );
                }

                $pairs[$permissionIds[$name] . ':' . $roles[$roleName]] = [
                    'id_permiso' => $permissionIds[$name],
                    'id_rol' => (int) $roles[$roleName],
                    'permiso' => $name,
                    'rol' => $roleName,
                ];
            }
        }

        return $pairs;
    }

    /** @param  array<string, array{id_permiso: int, id_rol: int, permiso: string, rol: string}>  $wanted */
    private function syncRolePermissions(array $wanted, Carbon $now, string $actorId, AuditLogService $audit): void
    {
        $open = $this->openPairs();

        foreach (array_diff_key($wanted, $open) as $pair) {
            DB::table('permiso_rol')->insert([
                'id_permiso' => $pair['id_permiso'],
                'id_rol' => $pair['id_rol'],
                'estado' => RecordStatus::ACTIVE,
                'fecha_inicio' => $now,
            ]);

            $audit->registrar('CREAR', 'permiso_rol', null, $pair + [
                'fecha_inicio' => $now->toIso8601String(),
                'fecha_fin' => null,
            ], $actorId);
        }

        foreach (array_diff_key($open, $wanted) as $pair) {
            DB::table('permiso_rol')
                ->where('id_permiso', $pair['id_permiso'])
                ->where('id_rol', $pair['id_rol'])
                ->whereNull('fecha_fin')
                ->update(['fecha_fin' => $now]);

            $audit->registrar('MODIFICAR', 'permiso_rol', $pair + ['fecha_fin' => null], $pair + [
                'fecha_fin' => $now->toIso8601String(),
            ], $actorId);
        }
    }

    /** @return array<string, array{id_permiso: int, id_rol: int, permiso: string, rol: string}> */
    private function openPairs(): array
    {
        $pairs = [];

        $rows = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id_permiso', '=', 'permiso_rol.id_permiso')
            ->join('rol', 'rol.id_rol', '=', 'permiso_rol.id_rol')
            ->whereNull('permiso_rol.fecha_fin')
            ->get(['permiso_rol.id_permiso', 'permiso_rol.id_rol', 'permiso.nombre_permiso', 'rol.nombre_rol']);

        foreach ($rows as $row) {
            $pairs[$row->id_permiso . ':' . $row->id_rol] = [
                'id_permiso' => (int) $row->id_permiso,
                'id_rol' => (int) $row->id_rol,
                'permiso' => (string) $row->nombre_permiso,
                'rol' => (string) $row->nombre_rol,
            ];
        }

        return $pairs;
    }

    /**
     * Pantallas de cada permiso: crea las que faltan en ui y deja ACTIVA o INACTIVA cada fila de ui_permiso.
     *
     * @param  array<string, array{roles: array<int, string>, pantallas: array<int, string>,
     *     descripcion: string}>  $definitions
     * @param  array<string, int>  $permissionIds
     */
    private function syncScreens(
        array $definitions,
        array $permissionIds,
        string $actorId,
        AuditLogService $audit
    ): void
    {
        $wanted = [];

        foreach ($definitions as $name => $definition) {
            foreach ($definition['pantallas'] as $screen) {
                $uiId = $this->uiId($screen, $actorId, $audit);
                $wanted[$uiId . ':' . $permissionIds[$name]] = [
                    'id_ui' => $uiId,
                    'id_permiso' => $permissionIds[$name],
                    'ui' => $screen,
                    'permiso' => $name,
                ];
            }
        }

        $existing = DB::table('ui_permiso')
            ->join('ui', 'ui.id_ui', '=', 'ui_permiso.id_ui')
            ->join('permiso', 'permiso.id_permiso', '=', 'ui_permiso.id_permiso')
            ->get([
                'ui_permiso.id_ui',
                'ui_permiso.id_permiso',
                'ui_permiso.estado',
                'ui.nombre_ui',
                'permiso.nombre_permiso',
            ])
            ->keyBy(fn ($row) => $row->id_ui . ':' . $row->id_permiso);

        foreach ($wanted as $key => $pair) {
            $row = $existing->get($key);

            if ($row === null) {
                DB::table('ui_permiso')->insert([
                    'id_ui' => $pair['id_ui'],
                    'id_permiso' => $pair['id_permiso'],
                    'estado' => RecordStatus::ACTIVE,
                ]);
                $audit->registrar('CREAR', 'ui_permiso', null, $pair + ['estado' => RecordStatus::ACTIVE], $actorId);
            } elseif ((string) $row->estado !== RecordStatus::ACTIVE) {
                $this->setScreenState($pair, (string) $row->estado, RecordStatus::ACTIVE, $actorId, $audit);
            }
        }

        foreach ($existing as $key => $row) {
            if (! isset($wanted[$key]) && (string) $row->estado === RecordStatus::ACTIVE) {
                $pair = [
                    'id_ui' => (int) $row->id_ui,
                    'id_permiso' => (int) $row->id_permiso,
                    'ui' => (string) $row->nombre_ui,
                    'permiso' => (string) $row->nombre_permiso,
                ];
                $this->setScreenState($pair, RecordStatus::ACTIVE, RecordStatus::INACTIVE, $actorId, $audit);
            }
        }
    }

    /** @param  array{id_ui: int, id_permiso: int, ui: string, permiso: string}  $pair */
    private function setScreenState(
        array $pair,
        string $from,
        string $to,
        string $actorId,
        AuditLogService $audit
    ): void
    {
        DB::table('ui_permiso')
            ->where('id_ui', $pair['id_ui'])
            ->where('id_permiso', $pair['id_permiso'])
            ->update(['estado' => $to]);

        $audit->registrar('MODIFICAR', 'ui_permiso', $pair + ['estado' => $from], $pair + ['estado' => $to], $actorId);
    }

    private function permissionId(string $name, string $description, string $actorId, AuditLogService $audit): int
    {
        $id = DB::table('permiso')->where('nombre_permiso', $name)->value('id_permiso');

        if ($id !== null) {
            return (int) $id;
        }

        $permission = ['nombre_permiso' => $name, 'descripcion' => $description, 'estado' => RecordStatus::ACTIVE];
        $id = (int) DB::table('permiso')->insertGetId($permission, 'id_permiso');

        $audit->registrar('CREAR', 'permiso', null, ['id_permiso' => $id] + $permission, $actorId);

        return $id;
    }

    private function uiId(string $name, string $actorId, AuditLogService $audit): int
    {
        $id = DB::table('ui')->where('nombre_ui', $name)->value('id_ui');

        if ($id !== null) {
            return (int) $id;
        }

        $ui = [
            'nombre_ui' => $name,
            'descripcion' => 'Pantalla «' . $name . '» de la interfaz.',
            'estado' => RecordStatus::ACTIVE,
        ];
        $id = (int) DB::table('ui')->insertGetId($ui, 'id_ui');

        $audit->registrar('CREAR', 'ui', null, ['id_ui' => $id] + $ui, $actorId);

        return $id;
    }
}
