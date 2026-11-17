<?php

namespace Database\Seeders\TestData;

use App\Support\ReportStatus;
use App\Support\SisCode;
use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Escenarios de QA del Sprint 5. Cubre HU-062 (rectificar un registro de la Central de Riesgo), HU-026
 * (invitar docentes colaboradores) y HU-027 (aceptar o rechazar la invitación).
 *
 * Primero carga TestDataSeeder (cuentas, catálogo, grupos, exámenes y Central de Riesgo base) y después
 * agrega los estudiantes y registros que hacen probable la búsqueda y la rectificación. Se ejecuta a mano,
 * solo en una base local:
 *
 *     php artisan db:seed --class="Database\Seeders\TestData\Sprint5QaSeeder"
 *
 * Escenarios (todos con datos ficticios):
 *   - María Pérez Gómez (con tilde) y Luis Perez Soliz (sin tilde) comparten el apellido: buscar «perez»
 *     devuelve a ambos más a José Pérez Mamani, del fixture base. María tiene un registro activo y Luis
 *     no tiene ninguno.
 *   - Carla Condori Vega tiene dos registros activos, listos para rectificar, y uno ya rectificado
 *     (retirado de la Central por un administrador, con su motivo).
 *   - Daniel Rojas Mendoza no tiene registros: sirve para el mensaje de estudiante sin registros.
 *
 * Volver a correrlo devuelve los escenarios a su estado inicial: se reescriben los reportes y los registros
 * de la central, así que lo que QA haya rectificado en estos tres registros vuelve a quedar activo (no así en
 * el resto de la Central). La bitácora no se toca.
 *
 * Escenario HU-026 (invitar docentes colaboradores). El creador es Marcelo Quiroga (10452), que dicta Cálculo I en
 * Sistemas y Economía:
 *   - Rosario Salazar (10487), Elena Camacho (10601), Rolando Vega (10602) y Carmen Ibáñez (10603) tienen grupos de
 *     Cálculo I en el período vigente: aparecen al buscar para el examen de Cálculo.
 *   - Gustavo Rocha (10533) dicta otra materia (Álgebra, Estadística): nunca aparece para ese examen.
 *   - «HU-026 Parcial Cálculo I» (9811) está PROGRAMADO y recibe las invitaciones PENDIENTE (Rosario), ACEPTADA
 *     (Elena) y RECHAZADA (Rolando, que no permitía agregar grupos). Carmen no tiene invitación: sirve para invitar.
 *   - «HU-026 Final Cálculo I» (9812) ya abrió el control de ingreso: no sale en el desplegable y su invitación a Carmen quedó
 *     SIN_EFECTO. Las fechas son futuras (diciembre de 2026) para que el planificador no cierre estos exámenes.
 * Volver a correrlo devuelve estos exámenes y sus invitaciones a su estado inicial. La bitácora no se toca.
 *
 * Escenario HU-027 (responder invitaciones). Rosario Salazar (10487) entra y ve su bandeja en los cuatro estados:
 *   - Pendientes: la de «HU-026 Parcial Cálculo I» (9811) y la de «HU-027 Parcial aula chica»
 *     (9821), que se rinde en el aula QA-8 (8 lugares) y ya tiene el grupo 1 (7 inscritos): al marcar su grupo 2
 *     (5 inscritos) la pantalla avisa que se supera la capacidad, sin impedir aceptar. Esta invitación pide la
 *     lista separada por grupo: tras aceptar con el grupo 2, al abrir el control de ingreso y durante el examen
 *     la lista se ve dividida en «Grupo 1 · Marcelo» y «Grupo 2 · Rosario».
 *   - Aceptada: «HU-027 Final Cálculo I» (9822), PROGRAMADO con el control de ingreso ya abierto y sin
 *     permitir grupos; Rosario lo ve en el control de ingreso como docente.
 *   - Rechazada: «HU-027 Recuperatorio» (9823), PROGRAMADO.
 *   - Sin efecto: la de «HU-026 Final Cálculo I» (9812), que abrió el control sin respuesta.
 *
 * Ids propios en el rango 9000–9999, sin pisar los de TestDataIds ni los de Sprint3QaSeeder.
 */
class Sprint5QaSeeder extends Seeder
{
    // estudiante.id_estudiante
    public const STUDENT_MARIA_PEREZ = 9700;
    public const STUDENT_LUIS_PEREZ = 9701;
    public const STUDENT_CARLA_CONDORI = 9702;
    public const STUDENT_DANIEL_ROJAS = 9703;

    // reporte_estudiante.id_reporte_est
    public const REPORT_CARLA_VOIDED = 9950;
    public const REPORT_CARLA_ACTIVE_ONE = 9951;
    public const REPORT_MARIA = 9952;
    public const REPORT_CARLA_ACTIVE_TWO = 9953;

    // central_riesgo.id_cr_estudiante
    public const RECORD_CARLA_VOIDED = 9961;
    public const RECORD_CARLA_ACTIVE_ONE = 9962;
    public const RECORD_MARIA = 9963;
    public const RECORD_CARLA_ACTIVE_TWO = 9964;

    // usuario.id_usuario (docentes ficticios de HU-026; los uuid comparten el prefijo de TestDataIds)
    public const TEACHER_ELENA = '00000000-0000-4000-8000-000000000101';
    public const TEACHER_ROLANDO = '00000000-0000-4000-8000-000000000102';
    public const TEACHER_CARMEN = '00000000-0000-4000-8000-000000000103';

    // grupo.id_grupo: Cálculo I, Sistemas, período vigente
    public const GROUP_CALCULUS_SYS_3 = 9421;
    public const GROUP_CALCULUS_SYS_4 = 9422;
    public const GROUP_CALCULUS_SYS_5 = 9423;

    // examen.id_examen
    public const EXAM_INVITE_SCHEDULED = 9811;
    public const EXAM_INVITE_ENTRY = 9812;
    public const EXAM_RESPONSE_SMALL_ROOM = 9821;
    public const EXAM_RESPONSE_ACCEPTED = 9822;
    public const EXAM_RESPONSE_REJECTED = 9823;

    // ambiente.id_ambiente: aula pequeña para el aviso de capacidad de HU-027
    public const CLASSROOM_SMALL = 9607;

    // invitacion_examen.id_invitacion
    public const INVITATION_PENDING = 9970;
    public const INVITATION_ACCEPTED = 9971;
    public const INVITATION_REJECTED = 9972;
    public const INVITATION_VOID = 9973;
    public const INVITATION_RESPONSE_PENDING = 9974;
    public const INVITATION_RESPONSE_ACCEPTED = 9975;
    public const INVITATION_RESPONSE_REJECTED = 9976;
    public const INVITATION_RESPONSE_VOID = 9977;

    private const EXAM_FINISHED = 9807;

    private const EXAM_ENTRY = 9805;

    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        // Base compartida: TestDataSeeder valida el entorno (local, PostgreSQL, nunca sciem_test).
        $this->call(TestDataSeeder::class);

        DB::transaction(function () {
            $this->seedStudents();
            $this->seedRecords();
            $this->seedCollaboratorInvitations();
            $this->seedInvitationResponses();
        });
    }

    private function seedStudents(): void
    {
        DB::table('estudiante')->upsert([
            $this->student(self::STUDENT_MARIA_PEREZ, '202170101', '7400101', 'María', 'Pérez', 'Gómez'),
            $this->student(self::STUDENT_LUIS_PEREZ, '202170102', '7400102', 'Luis', 'Perez', 'Soliz'),
            $this->student(self::STUDENT_CARLA_CONDORI, '202170103', '7400103', 'Carla', 'Condori', 'Vega'),
            $this->student(self::STUDENT_DANIEL_ROJAS, '202170104', '7400104', 'Daniel', 'Rojas', 'Mendoza'),
        ], ['id_estudiante']);
    }

    private function seedCollaboratorInvitations(): void
    {
        $teacherRoleId = DB::table('rol')->where('nombre_rol', 'Docente')->value('id_rol');
        $newTeachers = [self::TEACHER_ELENA, self::TEACHER_ROLANDO, self::TEACHER_CARMEN];

        DB::table('usuario')->upsert([
            $this->teacher(self::TEACHER_ELENA, 'Elena', 'Camacho', 'Rojas', '10601', 'elena.camacho'),
            $this->teacher(self::TEACHER_ROLANDO, 'Rolando', 'Vega', 'Méndez', '10602', 'rolando.vega'),
            $this->teacher(self::TEACHER_CARMEN, 'Carmen', 'Ibáñez', null, '10603', 'carmen.ibanez'),
        ], ['id_usuario']);

        DB::table('usuario_rol')->whereIn('id_usuario', $newTeachers)->delete();
        DB::table('usuario_rol')->insert(array_map(fn (string $id) => [
            'id_usuario' => $id,
            'id_rol' => $teacherRoleId,
            'fecha_inicio' => '2026-02-02 08:00:00-04',
            'fecha_fin' => null,
        ], $newTeachers));

        DB::table('grupo')->upsert([
            $this->calculusGroup(self::GROUP_CALCULUS_SYS_3, '3', self::TEACHER_ELENA),
            $this->calculusGroup(self::GROUP_CALCULUS_SYS_4, '4', self::TEACHER_ROLANDO),
            $this->calculusGroup(self::GROUP_CALCULUS_SYS_5, '5', self::TEACHER_CARMEN),
        ], ['id_grupo']);

        DB::table('examen')->upsert([
            $this->invitationExam(self::EXAM_INVITE_SCHEDULED, 'HU-026 Parcial Cálculo I', '2026-12-10', '08:00:00',
                '09:30:00', 90, 'PROGRAMADO'),
            $this->invitationExam(self::EXAM_INVITE_ENTRY, 'HU-026 Final Cálculo I', '2026-12-11', '10:00:00',
                '12:00:00', 120, 'PROGRAMADO', '2026-12-11 09:50:00-04'),
        ], ['id_examen']);

        $classrooms = [
            [self::EXAM_INVITE_SCHEDULED, 9601],
            [self::EXAM_INVITE_SCHEDULED, 9602],
            [self::EXAM_INVITE_ENTRY, 9605],
        ];

        foreach ($classrooms as [$examId, $classroomId]) {
            DB::table('examen_ambiente')->upsert(
                [['id_examen' => $examId, 'id_ambiente' => $classroomId]],
                ['id_examen', 'id_ambiente']
            );
        }

        foreach ([self::EXAM_INVITE_SCHEDULED, self::EXAM_INVITE_ENTRY] as $examId) {
            DB::table('grupo_examen')->upsert(
                [['id_examen' => $examId, 'id_grupo' => Ids::GROUP_CALCULUS_SYS_1]],
                ['id_grupo', 'id_examen']
            );
        }

        $invitationIds = [
            self::INVITATION_PENDING,
            self::INVITATION_ACCEPTED,
            self::INVITATION_REJECTED,
            self::INVITATION_VOID,
        ];

        DB::table('invitacion_examen')->whereIn('id_invitacion', $invitationIds)->delete();

        DB::table('invitacion_examen')->insert([
            $this->invitation(self::INVITATION_PENDING, self::EXAM_INVITE_SCHEDULED, Ids::TEACHER_2,
                'PENDIENTE', '2026-11-13 09:00:00-04'),
            $this->invitation(self::INVITATION_ACCEPTED, self::EXAM_INVITE_SCHEDULED, self::TEACHER_ELENA,
                'ACEPTADA', '2026-11-11 10:30:00-04', '2026-11-12 08:15:00-04'),
            $this->invitation(self::INVITATION_REJECTED, self::EXAM_INVITE_SCHEDULED, self::TEACHER_ROLANDO,
                'RECHAZADA', '2026-11-11 10:35:00-04', '2026-11-12 16:40:00-04', null, false),
            $this->invitation(self::INVITATION_VOID, self::EXAM_INVITE_ENTRY, self::TEACHER_CARMEN,
                'SIN_EFECTO', '2026-11-10 17:00:00-04', null, '2026-11-11 08:00:00-04'),
        ]);
    }

    private function seedInvitationResponses(): void
    {
        DB::table('ambiente')->upsert([[
            'id_ambiente' => self::CLASSROOM_SMALL,
            'nro_aula' => 'QA-8',
            'capacidad' => 8,
            'ubicacion' => 'Aula de prueba para el aviso de capacidad',
            'estado' => 'ACTIVO',
        ]], ['id_ambiente']);

        DB::table('examen')->upsert([
            $this->invitationExam(self::EXAM_RESPONSE_SMALL_ROOM, 'HU-027 Parcial aula chica', '2026-12-14',
                '08:00:00', '09:30:00', 90, 'PROGRAMADO'),
            $this->invitationExam(self::EXAM_RESPONSE_ACCEPTED, 'HU-027 Final Cálculo I', '2026-12-15', '10:00:00',
                '12:00:00', 120, 'PROGRAMADO', '2026-12-15 09:50:00-04'),
            $this->invitationExam(self::EXAM_RESPONSE_REJECTED, 'HU-027 Recuperatorio', '2026-12-16',
                '14:00:00', '15:30:00', 90, 'PROGRAMADO'),
        ], ['id_examen']);

        $examIds = [self::EXAM_RESPONSE_SMALL_ROOM, self::EXAM_RESPONSE_ACCEPTED, self::EXAM_RESPONSE_REJECTED];

        // El examen chico vuelve a tener solo el grupo 1, aunque QA haya aceptado sumando el grupo 2.
        DB::table('examen_ambiente')->whereIn('id_examen', $examIds)->delete();
        DB::table('grupo_examen')->whereIn('id_examen', $examIds)->delete();

        DB::table('examen_ambiente')->insert([
            ['id_examen' => self::EXAM_RESPONSE_SMALL_ROOM, 'id_ambiente' => self::CLASSROOM_SMALL],
            ['id_examen' => self::EXAM_RESPONSE_ACCEPTED, 'id_ambiente' => 9605],
            ['id_examen' => self::EXAM_RESPONSE_REJECTED, 'id_ambiente' => 9603],
        ]);
        DB::table('grupo_examen')->insert(array_map(
            fn (int $examId) => ['id_examen' => $examId, 'id_grupo' => Ids::GROUP_CALCULUS_SYS_1],
            $examIds
        ));

        $invitationIds = [
            self::INVITATION_RESPONSE_PENDING,
            self::INVITATION_RESPONSE_ACCEPTED,
            self::INVITATION_RESPONSE_REJECTED,
            self::INVITATION_RESPONSE_VOID,
        ];

        DB::table('invitacion_examen')->whereIn('id_invitacion', $invitationIds)->delete();

        DB::table('invitacion_examen')->insert([
            $this->invitation(self::INVITATION_RESPONSE_PENDING, self::EXAM_RESPONSE_SMALL_ROOM, Ids::TEACHER_2,
                'PENDIENTE', '2026-11-14 09:00:00-04', null, null, true, true),
            $this->invitation(self::INVITATION_RESPONSE_ACCEPTED, self::EXAM_RESPONSE_ACCEPTED, Ids::TEACHER_2,
                'ACEPTADA', '2026-11-10 11:00:00-04', '2026-11-10 15:20:00-04', null, false),
            $this->invitation(self::INVITATION_RESPONSE_REJECTED, self::EXAM_RESPONSE_REJECTED, Ids::TEACHER_2,
                'RECHAZADA', '2026-11-12 08:30:00-04', '2026-11-12 18:05:00-04'),
            $this->invitation(self::INVITATION_RESPONSE_VOID, self::EXAM_INVITE_ENTRY, Ids::TEACHER_2,
                'SIN_EFECTO', '2026-11-10 17:05:00-04', null, '2026-11-11 08:00:00-04'),
        ]);
    }

    /** @return array<string, mixed> */
    private function teacher(
        string $id,
        string $name,
        string $paternal,
        ?string $maternal,
        string $sis,
        string $emailUser
    ): array {
        return [
            'id_usuario' => $id,
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'apellido_materno' => $maternal,
            'correo' => "{$emailUser}@sciem.test",
            'contrasenia' => Ids::PASSWORD_HASH,
            'cod_sis' => SisCode::normalize($sis),
            'estado' => 'ACTIVO',
        ];
    }

    /** @return array<string, mixed> */
    private function calculusGroup(int $id, string $number, string $teacherId): array
    {
        return [
            'id_grupo' => $id,
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => Ids::SUBJECT_CALCULUS,
            'num_grupo' => $number,
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacherId,
            'id_gestion_periodo' => Ids::PERIOD_ACTIVE,
        ];
    }

    /** @return array<string, mixed> */
    private function invitationExam(
        int $id,
        string $name,
        string $date,
        string $start,
        string $end,
        int $duration,
        string $status,
        ?string $entryOpenedAt = null
    ): array {
        return [
            'id_examen' => $id,
            'nombre_examen' => $name,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion' => $duration,
            'normas' => 'Datos ficticios para desarrollo. Presentar identificación.',
            'id_tipo_examen' => 9701,
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => Ids::SUBJECT_CALCULUS,
            'id_usuario_docente' => Ids::TEACHER_FIXED,
            'estado' => $status,
            'ingreso_abierto_en' => $entryOpenedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function invitation(
        int $id,
        int $examId,
        string $invitedId,
        string $status,
        string $sentAt,
        ?string $respondedAt = null,
        ?string $voidedAt = null,
        bool $allowGroups = true,
        bool $listByGroup = false
    ): array {
        return [
            'id_invitacion' => $id,
            'id_examen' => $examId,
            'id_docente_creador' => Ids::TEACHER_FIXED,
            'id_docente_invitado' => $invitedId,
            'fecha_invitacion' => $sentAt,
            'fecha_respuesta' => $respondedAt,
            'fecha_sin_efecto' => $voidedAt,
            'estado' => $status,
            'permite_agregar_grupos' => $allowGroups,
            'lista_por_grupo' => $listByGroup,
        ];
    }

    /** @return array<string, mixed> */
    private function student(int $id, string $sis, string $ci, string $name, string $paternal, string $maternal): array
    {
        return [
            'id_estudiante' => $id,
            'cod_sis' => SisCode::normalize($sis),
            'ci' => $ci,
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'apellido_materno' => $maternal,
            'correo_institucional' => $sis . '@est.sciem.test',
            'telefono' => null,
            'estado' => 'ACTIVO',
        ];
    }

    private function seedRecords(): void
    {
        $types = DB::table('tipo_falta')->pluck('id_falta', 'nombre');
        $teacher = Ids::TEACHER_FIXED;
        $assistant = Ids::ASSISTANT_1;
        $approved = ReportStatus::APPROVED;

        $reports = [
            $this->report(self::REPORT_CARLA_VOIDED, self::STUDENT_CARLA_CONDORI, self::EXAM_FINISHED,
                $types['Material no permitido'], $assistant, 'Se la confundió con otra estudiante de la fila.',
                '2026-09-10 08:30:00-04', $approved, '2026-09-11 15:00:00-04', $teacher),
            $this->report(self::REPORT_CARLA_ACTIVE_ONE, self::STUDENT_CARLA_CONDORI, self::EXAM_ENTRY,
                $types['Conducta indebida'], $assistant, 'Interrumpió el ingreso de otros estudiantes.',
                '2026-09-21 09:10:00-04', $approved, '2026-09-22 10:00:00-04', $teacher),
            $this->report(self::REPORT_CARLA_ACTIVE_TWO, self::STUDENT_CARLA_CONDORI, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Miraba repetidamente la hoja de su compañero.',
                '2026-09-30 08:50:00-04', $approved, '2026-10-01 11:30:00-04', $teacher),
            $this->report(self::REPORT_MARIA, self::STUDENT_MARIA_PEREZ, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Copiaba respuestas de su compañero.',
                '2026-09-10 08:45:00-04', $approved, '2026-09-12 09:00:00-04', $teacher),
        ];

        DB::table('reporte_estudiante')->upsert($reports, ['id_reporte_est']);

        // El upsert reescribe también las columnas de rectificación: los activos vuelven a quedar sin rectificar.
        DB::table('central_riesgo')->upsert([
            $this->record(self::RECORD_CARLA_VOIDED, self::REPORT_CARLA_VOIDED, self::STUDENT_CARLA_CONDORI,
                '2026-09-11 15:00:00-04', true),
            $this->record(self::RECORD_CARLA_ACTIVE_ONE, self::REPORT_CARLA_ACTIVE_ONE, self::STUDENT_CARLA_CONDORI,
                '2026-09-22 10:00:00-04'),
            $this->record(self::RECORD_MARIA, self::REPORT_MARIA, self::STUDENT_MARIA_PEREZ, '2026-09-12 09:00:00-04'),
            $this->record(self::RECORD_CARLA_ACTIVE_TWO, self::REPORT_CARLA_ACTIVE_TWO, self::STUDENT_CARLA_CONDORI,
                '2026-10-01 11:30:00-04'),
        ], ['id_cr_estudiante']);
    }

    /** @return array<string, mixed> */
    private function report(
        int $id,
        int $studentId,
        int $examId,
        int $typeId,
        string $reporterId,
        string $description,
        string $reportedAt,
        string $status,
        string $reviewedAt,
        string $reviewerId
    ): array {
        return [
            'id_reporte_est' => $id,
            'id_estudiante' => $studentId,
            'id_examen' => $examId,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $reporterId,
            'id_intento' => null,
            'descripcion' => $description,
            'evidencia_url' => null,
            'evidencia_public_id' => null,
            'fecha_reporte' => $reportedAt,
            'estado' => $status,
            'fecha_revision' => $reviewedAt,
            'observacion_revision' => 'Confirmado.',
            'id_usuario_revisor' => $reviewerId,
        ];
    }

    /** @return array<string, mixed> */
    private function record(int $id, int $reportId, int $studentId, string $registeredAt, bool $voided = false): array
    {
        return [
            'id_cr_estudiante' => $id,
            'id_reporte_est' => $reportId,
            'id_estudiante' => $studentId,
            'fecha_registro' => $registeredAt,
            'rectificado_en' => $voided ? '2026-10-05 10:00:00-04' : null,
            'id_usuario_rectificador' => $voided ? Ids::ADMINISTRATOR : null,
            'motivo_rectificacion' => $voided
                ? 'Se confundió a la estudiante: el reporte correspondía a otra persona de la fila.'
                : null,
        ];
    }
}
