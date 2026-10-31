<?php

namespace Database\Seeders\TestData;

use App\Services\EntryControl\EntryControlSnapshotService;
use App\Support\ReportStatus;
use App\Support\SisCode;
use Carbon\Carbon;
use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Escenarios de QA del Sprint 3: un dato preparado para cada criterio de aceptación del SB3 v8.
 *
 * Primero carga TestDataSeeder (cuentas, catálogo, grupos, exámenes y Central de Riesgo base) y
 * después agrega lo que esos datos no cubren. Se ejecuta a mano, solo en una base local:
 *
 *     php artisan db:seed --class="Database\Seeders\TestData\Sprint3QaSeeder"
 *
 * Volver a correrlo devuelve los escenarios a su estado inicial: los exámenes de QA vuelven a
 * PROGRAMADO con fechas recalculadas, se borran sus ingresos, intentos, reportes y auxiliares, y los
 * grupos editados recuperan su número. Los tres exámenes del control de ingreso se programan para
 * HOY, relativos a la hora en que se corre el seeder; el scheduler (php artisan schedule:work) es
 * quien los abre, igual que en producción.
 *
 * Ids propios en el rango 9000–9999, sin pisar los de TestDataIds.
 *
 * clearScenarioRows() espera que la nómina de QA de HU-021 use los códigos SIS 202170001–202170028:
 * en cada corrida quita de Cálculo ECO 1 las inscripciones de los estudiantes con código 2021700xx.
 * El archivo de esa nómina no se versiona.
 */
class Sprint3QaSeeder extends Seeder
{
    // usuario.id_usuario
    public const ASSISTANT_STUDENT = '00000000-0000-4000-8000-000000000023';
    public const ROLE_CHANGE_ACCOUNT = '00000000-0000-4000-8000-000000000032';

    // grupo.id_grupo
    public const GROUP_ALGEBRA_SYS_35 = 9412;
    public const GROUP_ALGEBRA_SYS_3 = 9413;
    public const GROUP_STATISTICS_SYS_120 = 9414;
    public const GROUP_ALGEBRA_SYS_20 = 9415;

    // examen.id_examen
    public const EXAM_LIVE = 9811;
    public const EXAM_CAPACITY_FAILURE = 9812;
    public const EXAM_AUTO_OPEN = 9813;
    public const EXAM_OWN_PARCIAL = 9814;
    public const EXAM_FOREIGN = 9815;
    public const EXAM_ASSISTANT_BUSY = 9816;
    public const EXAM_OTHER_ROOM = 9817;
    public const EXAM_SAME_ROOM = 9818;
    public const EXAM_TWO_ROOMS = 9819;
    public const EXAM_FINISHED_ECO = 9820;

    // intento_ingreso.id_intento y reporte_estudiante.id_reporte_est
    public const EXTRA_ATTEMPT = 9912;
    public const EXTRA_PENDING_REPORT = 9930;

    // ambiente.id_ambiente (CatalogTestDataSeeder)
    private const ROOM_691A = 9601;
    private const ROOM_691B = 9602;
    private const ROOM_LAB_1 = 9604;
    private const ROOM_AUD_1 = 9605;

    private const ENROLLMENT_DATE = '2026-08-03';

    /** Primer id de los 118 estudiantes del grupo de 120 y de los 34 del grupo de 35. */
    private const FIRST_STUDENT_120 = 9530;
    private const FIRST_STUDENT_35 = 9650;

    /** Fechas fijas de los escenarios de registro y auxiliares (HU-024, HU-029, HU-030). */
    private const DATE_OWN_PARCIAL = '2026-11-04';
    private const DATE_FOREIGN = '2026-11-05';
    private const DATE_ASSISTANTS = '2026-11-06';

    private const FIRST_NAMES = [
        'Alejandro', 'Beatriz', 'Carlos', 'Daniela', 'Esteban', 'Fernanda', 'Gonzalo', 'Héctor',
        'Isabel', 'Joaquín', 'Lorena', 'Martín', 'Natalia', 'Óscar', 'Paola', 'Ramiro', 'Sofía',
        'Tomás', 'Valeria', 'Wilson',
    ];

    private const SURNAMES = [
        'Aguilar', 'Barrientos', 'Cardozo', 'Durán', 'Espinoza', 'Ferrel', 'Gutiérrez', 'Herbas',
        'Iriarte', 'Jáuregui', 'Lazcano', 'Maldonado', 'Navarro', 'Olivera', 'Paniagua', 'Quiroz',
        'Rodríguez', 'Sánchez', 'Téllez', 'Ugarte', 'Vásquez', 'Zurita',
    ];

    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        // Base compartida: TestDataSeeder valida el entorno (local, PostgreSQL, nunca sciem_test).
        $this->call(TestDataSeeder::class);

        $now = Carbon::now(config('sciem.zona_horaria'));

        DB::transaction(function () use ($now) {
            $this->clearScenarioRows();
            $this->seedAccounts();
            $this->seedStudents();
            $this->seedGroups();
            $this->seedExams($now);
            $this->seedAssistants();
            $this->seedFrozenRoster();
            $this->seedExtraPendingReport();
        });

        $this->forgetEntrySnapshots();
    }

    /**
     * Borra lo que QA genera al probar (ingresos, intentos, reportes, auxiliares) sobre los
     * exámenes de este seeder, para que cada corrida empiece igual. Se borra en el orden de las
     * llaves foráneas.
     */
    private function clearScenarioRows(): void
    {
        $exams = $this->examIds();

        $reportIds = DB::table('reporte_estudiante')->whereIn('id_examen', $exams)->pluck('id_reporte_est');
        DB::table('central_riesgo')->whereIn('id_reporte_est', $reportIds)->delete();
        DB::table('reporte_estudiante')->whereIn('id_reporte_est', $reportIds)->delete();
        DB::table('intento_ingreso')->whereIn('id_examen', $exams)->delete();
        DB::table('examen_estudiante')->whereIn('id_examen', $exams)->delete();
        DB::table('examen_auxiliar')->whereIn('id_examen', $exams)->delete();
        DB::table('examen_ambiente')->whereIn('id_examen', $exams)->delete();
        DB::table('grupo_examen')->whereIn('id_examen', $exams)->delete();

        // Exámenes que QA registró a mano en las fechas de prueba: se cancelan para que no choquen
        // con los escenarios de la siguiente corrida (HU-024 y HU-029/030).
        DB::table('examen')
            ->whereIn('fecha', [self::DATE_OWN_PARCIAL, self::DATE_FOREIGN, self::DATE_ASSISTANTS])
            ->whereNotIn('id_examen', $exams)
            ->whereIn('estado', ['PROGRAMADO', 'EN_INGRESO'])
            ->update(['estado' => 'CANCELADO']);

        // Inscripciones que deja la nómina de QA de HU-021 (códigos 202170001–202170028).
        $csvStudents = DB::table('estudiante')->where('cod_sis', 'like', '2021700%')->pluck('id_estudiante');
        DB::table('grupo_estudiante')
            ->where('id_grupo', Ids::GROUP_CALCULUS_ECO_1)
            ->whereIn('id_estudiante', $csvStudents)
            ->delete();
    }

    /**
     * HU-029: un auxiliar que también es estudiante del examen (mismo código SIS que Adriana Arnez).
     * HU-004: una cuenta propia para cambiarle el rol sin tocar las cuentas de las demás pruebas.
     */
    private function seedAccounts(): void
    {
        DB::table('usuario')->upsert([
            $this->user(self::ASSISTANT_STUDENT, 'Adriana', 'Arnez', 'Céspedes', '202150001', 'adriana.arnez'),
            $this->user(self::ROLE_CHANGE_ACCOUNT, 'Renata', 'Rojas', 'Vaca', '202000318', 'renata.rojas'),
        ], ['id_usuario']);

        $roles = DB::table('rol')->pluck('id_rol', 'nombre_rol');

        DB::table('usuario_rol')
            ->whereIn('id_usuario', [self::ASSISTANT_STUDENT, self::ROLE_CHANGE_ACCOUNT])
            ->delete();

        DB::table('usuario_rol')->insert([
            $this->role(self::ASSISTANT_STUDENT, $roles['Auxiliar'], '2026-08-03 08:00:00-04'),
            // Historial de dos tramos: fue Docente y desde el 15/09/2026 es Auxiliar.
            $this->role(
                self::ROLE_CHANGE_ACCOUNT,
                $roles['Docente'],
                '2026-02-02 08:00:00-04',
                '2026-09-15 08:00:00-04'
            ),
            $this->role(self::ROLE_CHANGE_ACCOUNT, $roles['Auxiliar'], '2026-09-15 08:00:00-04'),
        ]);
    }

    /** 118 estudiantes para el grupo de 120 y 34 para el de 35 (María Núñez es la primera). */
    private function seedStudents(): void
    {
        $rows = [];

        // 202160 + 3 dígitos: con %02d, del estudiante 100 en adelante el código pasaba a 10 dígitos.
        for ($i = 0; $i < 118; $i++) {
            $rows[] = $this->student(self::FIRST_STUDENT_120 + $i, sprintf('202160%03d', $i + 1), 7200001 + $i, $i);
        }

        $rows[] = [
            'id_estudiante' => self::FIRST_STUDENT_35,
            'cod_sis' => SisCode::normalize('202165001'),
            'ci' => '7300001',
            'nombre' => 'María',
            'apellido_paterno' => 'Núñez',
            'apellido_materno' => 'Rojas',
            'correo_institucional' => '202165001@est.sciem.test',
            'telefono' => null,
            'estado' => 'ACTIVO',
        ];

        for ($i = 1; $i < 35; $i++) {
            $rows[] = $this->student(self::FIRST_STUDENT_35 + $i, sprintf('2021650%02d', $i + 1), 7300001 + $i, $i + 7);
        }

        DB::table('estudiante')->upsert($rows, ['id_estudiante']);
    }

    private function seedGroups(): void
    {
        $groups = [
            // Grupo de 35 del docente fijo (HU-019 y HU-020); su número se restablece a 1 en cada corrida.
            $this->group(self::GROUP_ALGEBRA_SYS_35, Ids::SUBJECT_ALGEBRA, '1', Ids::TEACHER_FIXED),
            // Grupo 3 de la misma materia, carrera y período: el choque del criterio 3 de HU-019.
            $this->group(self::GROUP_ALGEBRA_SYS_3, Ids::SUBJECT_ALGEBRA, '3', Ids::TEACHER_2),
            // Grupo de 120 (HU-020 paginación y HU-032 con dos ambientes).
            $this->group(self::GROUP_STATISTICS_SYS_120, Ids::SUBJECT_STATISTICS, '1', Ids::TEACHER_FIXED),
            // Segundo grupo de Álgebra del docente fijo: 35 + 20 = 55 estudiantes (HU-024 CA-4 y CA-5).
            $this->group(self::GROUP_ALGEBRA_SYS_20, Ids::SUBJECT_ALGEBRA, '2', Ids::TEACHER_FIXED),
        ];

        DB::table('oferta_academica')->upsert([
            $this->offering(Ids::SUBJECT_ALGEBRA),
            $this->offering(Ids::SUBJECT_STATISTICS),
        ], ['id_carrera', 'id_materia', 'id_gestion_periodo'], ['estado']);

        DB::table('grupo')->upsert($groups, ['id_grupo']);

        $groupIds = [
            self::GROUP_ALGEBRA_SYS_35,
            self::GROUP_ALGEBRA_SYS_3,
            self::GROUP_STATISTICS_SYS_120,
            self::GROUP_ALGEBRA_SYS_20,
        ];
        DB::table('grupo_estudiante')->whereIn('id_grupo', $groupIds)->delete();

        $enrollments = [];

        // 120 = Adriana Arnez (9501, con foto) + José Pérez (9525, con antecedentes) + 118 nuevos.
        $roster120 = array_merge(
            [9501, Ids::STUDENT_JOSE_PEREZ],
            range(self::FIRST_STUDENT_120, self::FIRST_STUDENT_120 + 117)
        );

        foreach ($roster120 as $studentId) {
            $enrollments[] = $this->enrollment(self::GROUP_STATISTICS_SYS_120, $studentId);
        }

        foreach (range(self::FIRST_STUDENT_35, self::FIRST_STUDENT_35 + 34) as $studentId) {
            $enrollments[] = $this->enrollment(self::GROUP_ALGEBRA_SYS_35, $studentId);
        }

        // Los 20 del segundo grupo de Álgebra salen de los primeros del grupo de 120.
        foreach (range(self::FIRST_STUDENT_120, self::FIRST_STUDENT_120 + 19) as $studentId) {
            $enrollments[] = $this->enrollment(self::GROUP_ALGEBRA_SYS_20, $studentId);
        }

        DB::table('grupo_estudiante')->insert($enrollments);
    }

    private function seedExams(Carbon $now): void
    {
        // El control de ingreso se abre en el primer minuto (apertura de 30 min antes de un inicio cercano);
        // el de apertura automática, unos 25 minutos después de correr el seeder.
        $liveStart = $now->copy()->second(0)->addMinutes(15 - $now->minute % 5);
        $autoOpenStart = $liveStart->copy()->addMinutes(25);

        [$fixed, $rosario] = [Ids::TEACHER_FIXED, Ids::TEACHER_2];
        [$calculus, $statistics] = [Ids::SUBJECT_CALCULUS, Ids::SUBJECT_STATISTICS];
        $at = fn (string $date, string $time) => Carbon::parse("{$date} {$time}");
        [$parcialDay, $foreignDay] = [self::DATE_OWN_PARCIAL, self::DATE_FOREIGN];
        $assistantsDay = self::DATE_ASSISTANTS;

        /*
         * [id, nombre, inicio, duración, materia, docente, minutos de apertura]; todos de Sistemas salvo el
         * finalizado de Economía, que se agrega aparte.
         */
        $exams = [
            // HU-032, HU-042 y HU-030 CA-6: se abre apenas corre el scheduler (apertura de 30 min).
            [self::EXAM_LIVE, 'QA Control de ingreso', $liveStart, 120, $statistics, $fixed, 30],
            // HU-032 CA-2: la capacidad del LAB-1 (30) no alcanza para el grupo de 35.
            [self::EXAM_CAPACITY_FAILURE, 'QA Capacidad', $liveStart, 120, Ids::SUBJECT_ALGEBRA, $fixed, 30],
            // HU-032 CA-1: apertura de 10 minutos; se abre sola entre 25 y 30 minutos después del seeder.
            [self::EXAM_AUTO_OPEN, 'QA Apertura automática', $autoOpenStart, 60, Ids::SUBJECT_PROGRAMMING, $fixed, 10],
            // HU-024 CA-6: examen propio con el que choca el registro de QA.
            [self::EXAM_OWN_PARCIAL, 'Parcial 1', $at($parcialDay, '08:00'), 90, $calculus, $fixed, 10],
            // HU-024 CA-7: el 691A reservado por otro docente.
            [self::EXAM_FOREIGN, 'Parcial de Rosario 691A', $at($foreignDay, '09:00'), 90, $calculus, $rosario, 10],
            // HU-029/030: Iván ya controla el 691A de 08:00 a 10:00.
            [self::EXAM_ASSISTANT_BUSY, 'QA Auxiliar ocupado', $at($assistantsDay, '08:00'), 120, $calculus, $fixed,
                10],
            // HU-029 CA-5: único ambiente 691B, se superpone con el anterior.
            [self::EXAM_OTHER_ROOM, 'QA Aula distinta', $at($assistantsDay, '09:00'), 90, $calculus, $fixed, 10],
            // HU-029 CA-6 y HU-030 CA-5: único ambiente 691A; QA habilita ahí a Iván y luego abre el modal.
            [self::EXAM_SAME_ROOM, 'QA Misma aula', $at($assistantsDay, '09:00'), 90, $calculus, $fixed, 10],
            // HU-030 CA-1 a CA-4, CA-7 y CA-8: dos ambientes y dos auxiliares sin ambiente.
            [self::EXAM_TWO_ROOMS, 'QA Dos ambientes', $at($assistantsDay, '09:00'), 90, $calculus, $fixed, 10],
        ];

        $rows = array_map(fn (array $exam) => $this->exam(
            $exam[0],
            $exam[1],
            $exam[2],
            $exam[3],
            Ids::CAREER_SYSTEMS,
            $exam[4],
            $exam[5],
            $exam[6]
        ), $exams);

        // HU-021: examen finalizado del grupo 9403, con sus esperados congelados.
        $rows[] = $this->exam(
            self::EXAM_FINISHED_ECO,
            'QA Cálculo ECO final',
            $at('2026-10-01', '10:00'),
            90,
            Ids::CAREER_ECONOMICS,
            $calculus,
            $fixed,
            10,
            'FINALIZADO'
        );

        DB::table('examen')->upsert($rows, ['id_examen']);

        $rooms = [
            self::EXAM_LIVE => [self::ROOM_691A, self::ROOM_691B],
            self::EXAM_CAPACITY_FAILURE => [self::ROOM_LAB_1],
            self::EXAM_AUTO_OPEN => [self::ROOM_AUD_1],
            self::EXAM_OWN_PARCIAL => [self::ROOM_691A],
            self::EXAM_FOREIGN => [self::ROOM_691A],
            self::EXAM_ASSISTANT_BUSY => [self::ROOM_691A],
            self::EXAM_OTHER_ROOM => [self::ROOM_691B],
            self::EXAM_SAME_ROOM => [self::ROOM_691A],
            self::EXAM_TWO_ROOMS => [self::ROOM_691A, self::ROOM_691B],
            self::EXAM_FINISHED_ECO => [self::ROOM_AUD_1],
        ];

        $groups = [
            self::EXAM_LIVE => self::GROUP_STATISTICS_SYS_120,
            self::EXAM_CAPACITY_FAILURE => self::GROUP_ALGEBRA_SYS_35,
            self::EXAM_AUTO_OPEN => Ids::GROUP_PROGRAMMING_SYS_2,
            self::EXAM_OWN_PARCIAL => Ids::GROUP_CALCULUS_SYS_1,
            self::EXAM_FOREIGN => Ids::GROUP_CALCULUS_SYS_2,
            self::EXAM_ASSISTANT_BUSY => Ids::GROUP_CALCULUS_SYS_1,
            self::EXAM_OTHER_ROOM => Ids::GROUP_CALCULUS_SYS_1,
            self::EXAM_SAME_ROOM => Ids::GROUP_CALCULUS_SYS_1,
            self::EXAM_TWO_ROOMS => Ids::GROUP_CALCULUS_SYS_1,
            self::EXAM_FINISHED_ECO => Ids::GROUP_CALCULUS_ECO_1,
        ];

        foreach ($rooms as $examId => $roomIds) {
            foreach ($roomIds as $roomId) {
                DB::table('examen_ambiente')->insert(['id_examen' => $examId, 'id_ambiente' => $roomId]);
            }
        }

        foreach ($groups as $examId => $groupId) {
            DB::table('grupo_examen')->insert(['id_examen' => $examId, 'id_grupo' => $groupId]);
        }
    }

    private function seedAssistants(): void
    {
        $daniela = Ids::ASSISTANT_1;
        $ivan = Ids::ASSISTANT_2;

        DB::table('examen_auxiliar')->insert([
            // Control de ingreso: Daniela en el 691A e Iván en el 691B.
            $this->enablement(self::EXAM_LIVE, $daniela, self::ROOM_691A),
            $this->enablement(self::EXAM_LIVE, $ivan, self::ROOM_691B),
            // Iván ocupado en el 691A de 08:00 a 10:00 del 06/11.
            $this->enablement(self::EXAM_ASSISTANT_BUSY, $ivan, self::ROOM_691A),
            // Dos auxiliares habilitados sin ambiente (HU-030).
            $this->enablement(self::EXAM_TWO_ROOMS, $daniela, null),
            $this->enablement(self::EXAM_TWO_ROOMS, $ivan, null),
        ]);
    }

    /**
     * HU-021: el examen finalizado guarda una fila por estudiante esperado, como lo hace la
     * finalización desde la rama de la 021. Dos ingresaron y dos no.
     */
    private function seedFrozenRoster(): void
    {
        $students = DB::table('grupo_estudiante')
            ->where('id_grupo', Ids::GROUP_CALCULUS_ECO_1)
            ->whereIn('id_estudiante', [9513, 9514, 9515, 9516])
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante');

        $rows = [];

        foreach ($students as $index => $studentId) {
            $entered = $index < 2;
            $rows[] = [
                'id_examen' => self::EXAM_FINISHED_ECO,
                'id_estudiante' => $studentId,
                'id_grupo' => Ids::GROUP_CALCULUS_ECO_1,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => $entered ? 'INGRESO' : 'NO_INGRESO',
                'hora_ingreso' => $entered ? '09:55:00' : null,
                'id_ambiente' => $entered ? self::ROOM_AUD_1 : null,
                'id_usuario_controlador' => $entered ? Ids::TEACHER_FIXED : null,
                'registrado_en' => $entered ? '2026-10-01 09:55:00-04' : null,
            ];
        }

        DB::table('examen_estudiante')->insert($rows);
    }

    /** HU-045: un tercer reporte pendiente para la prueba de aceptación (3 pendientes). */
    private function seedExtraPendingReport(): void
    {
        $student = DB::table('estudiante')->where('id_estudiante', 9501)->first();
        $type = DB::table('tipo_falta')->where('nombre', 'Copia en examen')->value('id_falta');

        DB::table('central_riesgo')->where('id_reporte_est', self::EXTRA_PENDING_REPORT)->delete();

        DB::table('intento_ingreso')->upsert([[
            'id_intento' => self::EXTRA_ATTEMPT,
            'id_examen' => 9807,
            'id_estudiante' => 9501,
            'cod_sis' => $student->cod_sis,
            'ci_presentado' => $student->ci,
            'id_ambiente' => self::ROOM_691A,
            'id_usuario_controlador' => Ids::ASSISTANT_1,
            'motivo' => 'DECISION_CONTROLADOR',
            'observacion' => 'Llevaba un formulario escrito en la mano.',
            'registrado_en' => '2026-09-10 08:55:00-04',
        ]], ['id_intento']);

        DB::table('reporte_estudiante')->upsert([[
            'id_reporte_est' => self::EXTRA_PENDING_REPORT,
            'id_estudiante' => 9501,
            'id_examen' => 9807,
            'id_tipo_falta' => $type,
            'id_usuario_reportante' => Ids::ASSISTANT_1,
            'id_intento' => self::EXTRA_ATTEMPT,
            'descripcion' => 'Tenía un formulario con fórmulas escritas a mano.',
            'evidencia_url' => null,
            'evidencia_public_id' => null,
            'fecha_reporte' => '2026-09-10 09:00:00-04',
            'estado' => ReportStatus::PENDING,
            'fecha_revision' => null,
            'observacion_revision' => null,
            'id_usuario_revisor' => null,
        ]], ['id_reporte_est']);
    }

    /** Un snapshot viejo en Redis haría que el control de ingreso muestre datos de la corrida anterior. */
    private function forgetEntrySnapshots(): void
    {
        foreach ([self::EXAM_LIVE, self::EXAM_CAPACITY_FAILURE, self::EXAM_AUTO_OPEN] as $examId) {
            try {
                app(EntryControlSnapshotService::class)->deactivate($examId);
            } catch (Throwable $exception) {
                Log::warning('Sprint3QaSeeder: no se pudo limpiar el snapshot del control de ingreso', [
                    'id_examen' => $examId,
                    'motivo' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function examIds(): array
    {
        return [
            self::EXAM_LIVE, self::EXAM_CAPACITY_FAILURE, self::EXAM_AUTO_OPEN, self::EXAM_OWN_PARCIAL,
            self::EXAM_FOREIGN, self::EXAM_ASSISTANT_BUSY, self::EXAM_OTHER_ROOM, self::EXAM_SAME_ROOM,
            self::EXAM_TWO_ROOMS, self::EXAM_FINISHED_ECO,
        ];
    }

    private function user(
        string $id,
        string $name,
        string $paternalSurname,
        string $maternalSurname,
        string $sisCode,
        string $emailUser
    ): array {
        return [
            'id_usuario' => $id,
            'nombre' => $name,
            'apellido_paterno' => $paternalSurname,
            'apellido_materno' => $maternalSurname,
            'correo' => "{$emailUser}@sciem.test",
            'contrasenia' => Ids::PASSWORD_HASH,
            'cod_sis' => SisCode::normalize($sisCode),
            'estado' => 'ACTIVO',
        ];
    }

    private function role(string $userId, int $roleId, string $start, ?string $end = null): array
    {
        return ['id_usuario' => $userId, 'id_rol' => $roleId, 'fecha_inicio' => $start, 'fecha_fin' => $end];
    }

    private function student(int $id, string $sisCode, int $ci, int $seed): array
    {
        return [
            'id_estudiante' => $id,
            'cod_sis' => SisCode::normalize($sisCode),
            'ci' => (string) $ci,
            'nombre' => self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)],
            'apellido_paterno' => self::SURNAMES[$seed % count(self::SURNAMES)],
            'apellido_materno' => self::SURNAMES[intdiv($seed, count(self::SURNAMES)) % count(self::SURNAMES)],
            'correo_institucional' => "{$sisCode}@est.sciem.test",
            'telefono' => null,
            'estado' => 'ACTIVO',
        ];
    }

    private function group(int $id, int $subjectId, string $number, string $teacherId): array
    {
        return [
            'id_grupo' => $id,
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => $subjectId,
            'num_grupo' => $number,
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacherId,
            'id_gestion_periodo' => Ids::PERIOD_ACTIVE,
        ];
    }

    private function offering(int $subjectId): array
    {
        return [
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => $subjectId,
            'id_gestion_periodo' => Ids::PERIOD_ACTIVE,
            'estado' => 'ACTIVO',
        ];
    }

    private function enrollment(int $groupId, int $studentId): array
    {
        return [
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => self::ENROLLMENT_DATE,
            'estado' => 'ACTIVO',
        ];
    }

    private function exam(
        int $id,
        string $name,
        Carbon $startsAt,
        int $duration,
        int $careerId,
        int $subjectId,
        string $teacherId,
        int $openingMinutes = 10,
        string $status = 'PROGRAMADO'
    ): array {
        return [
            'id_examen' => $id,
            'nombre_examen' => $name,
            'fecha' => $startsAt->toDateString(),
            'hora_inicio' => $startsAt->format('H:i:s'),
            'hora_fin' => $startsAt->copy()->addMinutes($duration)->format('H:i:s'),
            'duracion' => $duration,
            'normas' => 'Escenario de QA del Sprint 3. Presentar identificación.',
            'id_tipo_examen' => 9701,
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'id_usuario_docente' => $teacherId,
            'estado' => $status,
            'minutos_apertura' => $openingMinutes,
        ];
    }

    private function enablement(int $examId, string $assistantId, ?int $roomId): array
    {
        return [
            'id_examen' => $examId,
            'id_usuario' => $assistantId,
            'id_usuario_docente_habilita' => Ids::TEACHER_FIXED,
            'fecha_habilitacion' => '2026-10-19 08:00:00-04',
            'id_ambiente' => $roomId,
        ];
    }
}
