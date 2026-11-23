<?php

namespace Database\Seeders\TestData;

use App\Support\ReportStatus;
use App\Support\SisCode;
use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
 * Escenario HU-062 con confirmación del docente. Marcelo Quiroga (10452) aprobó los reportes y es quien responde
 * las solicitudes de rectificación. Los registros son de Valeria Arce Montaño (9704):
 *   - «Solicitud pendiente» (registro 9965): enviada ayer, vence en 6 días. El reporte tiene evidencia descargable.
 *   - «Solicitud aceptada» (9966): el registro ya salió de la Central (rectificado por la aceptación).
 *   - «Solicitud rechazada con imagen» (9967): el docente rechazó con motivo y una imagen de evidencia.
 *   - «Solicitud vencida» (9968): venció sin respuesta; el registro sigue en la Central.
 *   - «Dos rechazos previos» (9969): dos solicitudes rechazadas y ninguna pendiente; sirve para enviar una nueva y
 *     para ver el aviso «Este registro ya tuvo 2 solicitudes de rectificación rechazadas».
 * Mauricio Terán Peña (9705) tiene dos registros cuyo docente aprobador ya no es docente activo:
 *   - Héctor Mamani (10604) dejó de ser docente hace 60 días: aún no se puede rectificar sin su confirmación.
 *   - Beatriz Zurita (10605) dejó de ser docente hace 400 días: ya se puede rectificar sin su confirmación.
 * Las fechas de las solicitudes se calculan al sembrar, así que conviene volver a sembrar si pasaron días. La
 * imagen del rechazo y la evidencia del reporte se guardan en el disco privado (storage/app/evidencias).
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

    // HU-062 con confirmación del docente: estudiantes, reportes, registros, docentes inactivos y solicitudes
    public const STUDENT_VALERIA_ARCE = 9704;
    public const STUDENT_MAURICIO_TERAN = 9705;

    public const REPORT_REQUEST_PENDING = 9954;
    public const REPORT_REQUEST_ACCEPTED = 9955;
    public const REPORT_REQUEST_REJECTED = 9956;
    public const REPORT_REQUEST_EXPIRED = 9957;
    public const REPORT_REQUEST_HISTORY = 9958;
    public const REPORT_INACTIVE_RECENT = 9959;
    public const REPORT_INACTIVE_OLD = 9960;

    public const RECORD_REQUEST_PENDING = 9965;
    public const RECORD_REQUEST_ACCEPTED = 9966;
    public const RECORD_REQUEST_REJECTED = 9967;
    public const RECORD_REQUEST_EXPIRED = 9968;
    public const RECORD_REQUEST_HISTORY = 9969;
    public const RECORD_INACTIVE_RECENT = 9970;
    public const RECORD_INACTIVE_OLD = 9971;

    public const TEACHER_INACTIVE_RECENT = '00000000-0000-4000-8000-000000000104';
    public const TEACHER_INACTIVE_OLD = '00000000-0000-4000-8000-000000000105';

    // solicitud_rectificacion.id_solicitud_rect
    public const REQUEST_PENDING = 9980;
    public const REQUEST_ACCEPTED = 9981;
    public const REQUEST_REJECTED = 9982;
    public const REQUEST_EXPIRED = 9983;
    public const REQUEST_HISTORY_FIRST = 9984;
    public const REQUEST_HISTORY_SECOND = 9985;

    private const REJECTION_IMAGE = 'evidencias/rectificaciones/qa-rechazo-9982.png';

    private const REPORT_IMAGE = 'evidencias/reportes/qa-reporte-9954.png';

    /** PNG de 1x1 píxel: la evidencia de QA solo necesita ser una imagen válida. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGA'
        . 'hKmMIQAAAABJRU5ErkJggg==';

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
            $this->seedRectificationRequests();
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

    /**
     * Escenarios de la doble confirmación (HU-062). Las solicitudes se borran y se vuelven a crear (con sus fechas
     * relativas a hoy) y los registros se reescriben, así que volver a sembrar devuelve todo al estado inicial.
     */
    private function seedRectificationRequests(): void
    {
        $types = DB::table('tipo_falta')->pluck('id_falta', 'nombre');
        $assistant = Ids::ASSISTANT_1;
        $approved = ReportStatus::APPROVED;
        $teacherRoleId = DB::table('rol')->where('nombre_rol', 'Docente')->value('id_rol');

        DB::table('estudiante')->upsert([
            $this->student(self::STUDENT_VALERIA_ARCE, '202170105', '7400105', 'Valeria', 'Arce', 'Montaño'),
            $this->student(self::STUDENT_MAURICIO_TERAN, '202170106', '7400106', 'Mauricio', 'Terán', 'Peña'),
        ], ['id_estudiante']);

        $inactive = [self::TEACHER_INACTIVE_RECENT, self::TEACHER_INACTIVE_OLD];
        DB::table('usuario')->upsert([
            $this->teacher(self::TEACHER_INACTIVE_RECENT, 'Héctor', 'Mamani', 'Choque', '10604', 'hector.mamani'),
            $this->teacher(self::TEACHER_INACTIVE_OLD, 'Beatriz', 'Zurita', null, '10605', 'beatriz.zurita'),
        ], ['id_usuario']);

        DB::table('usuario_rol')->whereIn('id_usuario', $inactive)->delete();
        DB::table('usuario_rol')->insert([
            [
                'id_usuario' => self::TEACHER_INACTIVE_RECENT,
                'id_rol' => $teacherRoleId,
                'fecha_inicio' => now()->subDays(300),
                'fecha_fin' => now()->subDays(60),
            ],
            [
                'id_usuario' => self::TEACHER_INACTIVE_OLD,
                'id_rol' => $teacherRoleId,
                'fecha_inicio' => now()->subDays(700),
                'fecha_fin' => now()->subDays(400),
            ],
        ]);
        DB::table('docente_inactivo_deteccion')->whereIn('id_usuario', $inactive)->delete();

        $teacher = Ids::TEACHER_FIXED;
        $reports = [
            $this->report(self::REPORT_REQUEST_PENDING, self::STUDENT_VALERIA_ARCE, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Usaba apuntes escondidos bajo la mesa.',
                '2026-10-12 08:40:00-04', $approved, '2026-10-13 10:00:00-04', $teacher),
            $this->report(self::REPORT_REQUEST_ACCEPTED, self::STUDENT_VALERIA_ARCE, self::EXAM_FINISHED,
                $types['Conducta indebida'], $assistant, 'Habló con un compañero durante la prueba.',
                '2026-10-12 08:50:00-04', $approved, '2026-10-13 10:10:00-04', $teacher),
            $this->report(self::REPORT_REQUEST_REJECTED, self::STUDENT_VALERIA_ARCE, self::EXAM_FINISHED,
                $types['Material no permitido'], $assistant, 'Tenía un celular encendido sobre la mesa.',
                '2026-10-12 09:00:00-04', $approved, '2026-10-13 10:20:00-04', $teacher),
            $this->report(self::REPORT_REQUEST_EXPIRED, self::STUDENT_VALERIA_ARCE, self::EXAM_FINISHED,
                $types['Conducta indebida'], $assistant, 'Se levantó sin permiso del aula.',
                '2026-10-12 09:10:00-04', $approved, '2026-10-13 10:30:00-04', $teacher),
            $this->report(self::REPORT_REQUEST_HISTORY, self::STUDENT_VALERIA_ARCE, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Intercambió hojas con otra estudiante.',
                '2026-10-12 09:20:00-04', $approved, '2026-10-13 10:40:00-04', $teacher),
            $this->report(self::REPORT_INACTIVE_RECENT, self::STUDENT_MAURICIO_TERAN, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Miraba la hoja de su compañero de la derecha.',
                '2026-02-10 08:30:00-04', $approved, '2026-02-11 09:00:00-04', self::TEACHER_INACTIVE_RECENT),
            $this->report(self::REPORT_INACTIVE_OLD, self::STUDENT_MAURICIO_TERAN, self::EXAM_FINISHED,
                $types['Material no permitido'], $assistant, 'Usó una calculadora programable no autorizada.',
                '2025-08-10 08:30:00-04', $approved, '2025-08-11 09:00:00-04', self::TEACHER_INACTIVE_OLD),
        ];
        $reports[0]['evidencia_url'] = 'https://sciem.test/qa/evidencia-9954.png';
        $reports[0]['evidencia_public_id'] = self::REPORT_IMAGE;

        DB::table('reporte_estudiante')->upsert($reports, ['id_reporte_est']);

        DB::table('central_riesgo')->upsert([
            $this->record(self::RECORD_REQUEST_PENDING, self::REPORT_REQUEST_PENDING, self::STUDENT_VALERIA_ARCE,
                '2026-10-13 10:00:00-04'),
            $this->record(self::RECORD_REQUEST_ACCEPTED, self::REPORT_REQUEST_ACCEPTED, self::STUDENT_VALERIA_ARCE,
                '2026-10-13 10:10:00-04', true),
            $this->record(self::RECORD_REQUEST_REJECTED, self::REPORT_REQUEST_REJECTED, self::STUDENT_VALERIA_ARCE,
                '2026-10-13 10:20:00-04'),
            $this->record(self::RECORD_REQUEST_EXPIRED, self::REPORT_REQUEST_EXPIRED, self::STUDENT_VALERIA_ARCE,
                '2026-10-13 10:30:00-04'),
            $this->record(self::RECORD_REQUEST_HISTORY, self::REPORT_REQUEST_HISTORY, self::STUDENT_VALERIA_ARCE,
                '2026-10-13 10:40:00-04'),
            $this->record(self::RECORD_INACTIVE_RECENT, self::REPORT_INACTIVE_RECENT, self::STUDENT_MAURICIO_TERAN,
                '2026-02-11 09:00:00-04'),
            $this->record(self::RECORD_INACTIVE_OLD, self::REPORT_INACTIVE_OLD, self::STUDENT_MAURICIO_TERAN,
                '2025-08-11 09:00:00-04'),
        ], ['id_cr_estudiante']);

        // La aceptada anuló su registro con el administrador solicitante y el motivo de su solicitud.
        DB::table('central_riesgo')->where('id_cr_estudiante', self::RECORD_REQUEST_ACCEPTED)->update([
            'rectificado_en' => now()->subDays(9),
            'motivo_rectificacion' => 'Se solicita retirar: el reporte correspondía a otra persona de la fila.',
        ]);

        Storage::disk('local')->put(self::REPORT_IMAGE, base64_decode(self::PNG_BASE64));
        Storage::disk('local')->put(self::REJECTION_IMAGE, base64_decode(self::PNG_BASE64));

        $requestIds = [
            self::REQUEST_PENDING, self::REQUEST_ACCEPTED, self::REQUEST_REJECTED, self::REQUEST_EXPIRED,
            self::REQUEST_HISTORY_FIRST, self::REQUEST_HISTORY_SECOND,
        ];
        DB::table('solicitud_rectificacion')->whereIn('id_solicitud_rect', $requestIds)->delete();

        DB::table('solicitud_rectificacion')->insert([
            $this->request(self::REQUEST_PENDING, self::RECORD_REQUEST_PENDING, 'PENDIENTE', 1, 7,
                'Se solicita retirar el registro: la estudiante no era quien usaba los apuntes.'),
            $this->request(self::REQUEST_ACCEPTED, self::RECORD_REQUEST_ACCEPTED, 'ACEPTADA', 10, 7,
                'Se solicita retirar: el reporte correspondía a otra persona de la fila.', 9),
            $this->request(self::REQUEST_REJECTED, self::RECORD_REQUEST_REJECTED, 'RECHAZADA', 8, 7,
                'Se solicita retirar el registro porque el celular era de la estudiante contigua.', 7,
                'El celular estaba a nombre de la estudiante y consta en la fotografía del aula.',
                self::REJECTION_IMAGE),
            $this->request(self::REQUEST_EXPIRED, self::RECORD_REQUEST_EXPIRED, 'VENCIDA', 20, 7,
                'Se solicita retirar el registro: la estudiante tenía autorización para salir.'),
            $this->request(self::REQUEST_HISTORY_FIRST, self::RECORD_REQUEST_HISTORY, 'RECHAZADA', 60, 7,
                'Se solicita retirar el registro: no se identificó a la estudiante con certeza.', 58,
                'La estudiante fue identificada por el auxiliar y por la lista de asistencia.'),
            $this->request(self::REQUEST_HISTORY_SECOND, self::RECORD_REQUEST_HISTORY, 'RECHAZADA', 30, 7,
                'Se solicita retirar el registro: el intercambio de hojas no está comprobado.', 28,
                'El intercambio quedó registrado por dos auxiliares; se mantiene el registro.'),
        ]);
    }

    /**
     * Una solicitud enviada hace `$sentDaysAgo` días. `$answeredDaysAgo` y los datos del rechazo solo aplican a las
     * ya respondidas.
     *
     * @return array<string, mixed>
     */
    private function request(
        int $id,
        int $recordId,
        string $status,
        int $sentDaysAgo,
        int $validDays,
        string $reason,
        ?int $answeredDaysAgo = null,
        ?string $rejectionReason = null,
        ?string $image = null
    ): array {
        return [
            'id_solicitud_rect' => $id,
            'id_cr_estudiante' => $recordId,
            'id_usuario_solicitante' => Ids::ADMINISTRATOR,
            'motivo_solicitud' => $reason,
            'id_usuario_docente' => Ids::TEACHER_FIXED,
            'estado' => $status,
            'fecha_solicitud' => now()->subDays($sentDaysAgo),
            'vence_en' => now()->subDays($sentDaysAgo)->addDays($validDays),
            'fecha_respuesta' => $answeredDaysAgo === null ? null : now()->subDays($answeredDaysAgo),
            'motivo_rechazo' => $rejectionReason,
            'evidencia_public_id' => $image,
        ];
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
