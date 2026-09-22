<?php

namespace Database\Seeders\TestData;

use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cuentas de prueba y su historial de roles.
 *
 * Formatos de cod_sis: Administrador alfanumérico, docentes de 5 dígitos, auxiliares y
 * demás cuentas de 9 dígitos. Ninguno coincide con los códigos que FakeSisGateway
 * reconoce, que quedan libres para registrar cuentas nuevas (HU-001).
 */
class AccountTestDataSeeder extends Seeder
{
    public function run()
    {
        DB::table('usuario')->upsert([
            $this->user(Ids::ADMINISTRATOR, 'Valeria', 'Montaño', 'Ríos', 'ADM0001', 'valeria.montano'),
            $this->user(Ids::TEACHER_FIXED, 'Marcelo', 'Quiroga', 'Andrade', '10452', 'marcelo.quiroga'),
            $this->user(Ids::TEACHER_2, 'Rosario', 'Salazar', 'Vidal', '10487', 'rosario.salazar'),
            $this->user(Ids::TEACHER_3, 'Gustavo', 'Rocha', null, '10533', 'gustavo.rocha'),
            $this->user(Ids::ASSISTANT_1, 'Daniela', 'Ferrufino', 'Soliz', '201800451', 'daniela.ferrufino'),
            $this->user(Ids::ASSISTANT_2, 'Iván', 'Choque', 'Mamani', '201900782', 'ivan.choque'),
            $this->user(Ids::WITHOUT_ROLE, 'Lucía', 'Terrazas', 'Paz', '202000315', 'lucia.terrazas'),
            $this->user(Ids::DISABLED, 'Óscar', 'Villarroel', 'Gutiérrez', '10398', 'oscar.villarroel', 'INACTIVO'),
        ], ['id_usuario']);

        // RoleSeeder deja fecha_registro en CURRENT_TIMESTAMP: se fija para que todos tengan el mismo valor.
        DB::table('rol')
            ->whereIn('nombre_rol', ['Administrador', 'Docente', 'Auxiliar'])
            ->update(['fecha_registro' => '2026-02-02 08:00:00-04']);

        $roles = DB::table('rol')->pluck('id_rol', 'nombre_rol');

        foreach (['Administrador', 'Docente', 'Auxiliar'] as $role) {
            if (! isset($roles[$role])) {
                throw new RuntimeException("Falta el rol {$role}: ejecute RoleSeeder antes de los datos de prueba.");
            }
        }

        /*
         * El historial de roles se restablece completo en cada ejecución: si alguien
         * cambió el rol de una de estas cuentas desde la aplicación, se vuelve al estado
         * canónico en lugar de acumular dos roles vigentes.
         */
        DB::table('usuario_rol')->whereIn('id_usuario', Ids::userIds())->delete();

        DB::table('usuario_rol')->insert([
            $this->role(Ids::ADMINISTRATOR, $roles['Administrador'], '2026-02-02 08:00:00-04'),
            // El docente fijo fue auxiliar antes: su detalle muestra un historial de dos tramos.
            $this->role(Ids::TEACHER_FIXED, $roles['Auxiliar'], '2025-08-04 08:00:00-04', '2026-01-30 18:00:00-04'),
            $this->role(Ids::TEACHER_FIXED, $roles['Docente'], '2026-02-02 08:00:00-04'),
            $this->role(Ids::TEACHER_2, $roles['Docente'], '2026-02-02 08:00:00-04'),
            $this->role(Ids::TEACHER_3, $roles['Docente'], '2026-02-02 08:00:00-04'),
            $this->role(Ids::ASSISTANT_1, $roles['Auxiliar'], '2026-02-02 08:00:00-04'),
            $this->role(Ids::ASSISTANT_2, $roles['Auxiliar'], '2026-08-03 08:00:00-04'),
            // La cuenta deshabilitada conserva su rol pasado, ya cerrado; WITHOUT_ROLE no tiene ninguno.
            $this->role(Ids::DISABLED, $roles['Docente'], '2025-02-03 08:00:00-04', '2025-12-19 18:00:00-04'),
        ]);
    }

    private function user(
        string $id,
        string $name,
        string $paternalSurname,
        ?string $maternalSurname,
        string $sisCode,
        string $emailUser,
        string $status = 'ACTIVO'
    ): array {
        return [
            'id_usuario' => $id,
            'nombre' => $name,
            'apellido_paterno' => $paternalSurname,
            'apellido_materno' => $maternalSurname,
            'correo' => "{$emailUser}@sciem.test",
            'contrasenia' => Ids::PASSWORD_HASH,
            'cod_sis' => $sisCode,
            'estado' => $status,
        ];
    }

    private function role(string $userId, int $roleId, string $start, ?string $end = null): array
    {
        return ['id_usuario' => $userId, 'id_rol' => $roleId, 'fecha_inicio' => $start, 'fecha_fin' => $end];
    }
}
