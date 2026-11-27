<?php

namespace Tests\Feature\Exams;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamEligibleListExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/examenes/habilitados')->assertUnauthorized();
    }

    public function test_no_docente_devuelve_403(): void
    {
        // Un usuario con rol Auxiliar no pasa isTeacher().
        $auxiliarId = '44444444-4444-4444-8444-444444444444';

        DB::table('usuario')->insert([
            'id_usuario' => $auxiliarId,
            'nombre' => 'Auxiliar',
            'apellido_paterno' => 'Prueba',
            'correo' => 'auxiliar@umss.edu',
            'contrasenia' => 'x',
            'cod_sis' => '202099999',
            'estado' => 'ACTIVO',
        ]);

        $this->seed(RoleSeeder::class);

        DB::table('usuario_rol')->insert([
            'id_usuario' => $auxiliarId,
            'id_rol' => Role::where('nombre_rol', Role::AUXILIAR)->value('id_rol'),
            'fecha_inicio' => '2026-01-10 08:00:00',
        ]);

        $this->actAsUserId($auxiliarId);

        $this->getJson('/api/examenes/habilitados')->assertForbidden();
    }

    public function test_excluye_finalizados_y_cancelados(): void
    {
        $this->actAsTeacher($this->docenteId);

        $programado = $this->createExam(['estado' => Exam::PROGRAMADO]);

        $this->createExam(['estado' => Exam::FINALIZADO]);
        $this->createExam(['estado' => Exam::CANCELADO]);

        $response = $this->getJson('/api/examenes/habilitados')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($programado->id_examen, $response->json('data.0.id_examen'));
    }

    public function test_devuelve_solo_programados_con_el_control_de_ingreso_sin_abrir(): void
    {
        $this->actAsTeacher($this->docenteId);

        $proximo = $this->createExam(['estado' => Exam::PROGRAMADO]);
        $despues = $this->createExam([
            'estado' => Exam::PROGRAMADO,
            'nombre_examen' => 'Más adelante',
            'fecha' => $this->futureDate(8),
        ]);
        $abierto = $this->createExam([
            'estado' => Exam::PROGRAMADO,
            'nombre_examen' => 'Con el control abierto',
            'fecha' => $this->futureDate(9),
        ]);
        $this->putExamInState($abierto->id_examen, 'INGRESO_ABIERTO');
        $enCurso = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'nombre_examen' => 'En curso',
            'fecha' => $this->futureDate(10),
        ]);

        $response = $this->getJson('/api/examenes/habilitados')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = array_column($response->json('data'), 'id_examen');

        $this->assertNotContains($abierto->id_examen, $ids);
        $this->assertNotContains($enCurso->id_examen, $ids);

        // Orden ascendente por fecha.
        $this->assertSame([$proximo->id_examen, $despues->id_examen], $ids);
    }

    public function test_devuelve_el_shape_minimo_del_selector(): void
    {
        $this->actAsTeacher($this->docenteId);

        $this->createExam(['estado' => Exam::PROGRAMADO]);

        $item = $this->getJson('/api/examenes/habilitados')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('id_examen', $item);
        $this->assertArrayHasKey('nombre', $item);
        $this->assertArrayHasKey('materia', $item);
        $this->assertArrayHasKey('carrera', $item);
        $this->assertArrayHasKey('fecha', $item);
        $this->assertArrayHasKey('hora_inicio', $item);
        $this->assertArrayHasKey('estado', $item);
        $this->assertArrayHasKey('grupos', $item);
        $this->assertIsArray($item['grupos']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $item['fecha']);
        $this->assertSame(Exam::PROGRAMADO, $item['estado']);
    }

    public function test_devuelve_los_ambientes_de_cada_examen(): void
    {
        $this->actAsTeacher($this->docenteId);

        $conAmbientes = $this->createExam(['estado' => Exam::PROGRAMADO], [$this->otraAulaId, $this->aulaId]);
        $sinAmbientes = $this->createExam([
            'estado' => Exam::PROGRAMADO,
            'nombre_examen' => 'Sin ambientes',
            'fecha' => $this->futureDate(8),
        ]);

        $items = collect($this->getJson('/api/examenes/habilitados')->assertOk()->json('data'))
            ->keyBy('id_examen');

        $this->assertSame(
            [
                ['id' => $this->aulaId, 'nro_aula' => '691A'],
                ['id' => $this->otraAulaId, 'nro_aula' => '692B'],
            ],
            $items[$conAmbientes->id_examen]['ambientes']
        );
        $this->assertSame([], $items[$sinAmbientes->id_examen]['ambientes']);
        $this->assertArrayNotHasKey('hora_fin', $items[$conAmbientes->id_examen]);
    }

    public function test_la_cantidad_de_consultas_no_crece_con_los_examenes(): void
    {
        $this->actAsTeacher($this->docenteId);

        $this->createExam(['estado' => Exam::PROGRAMADO], [$this->makeClassroom('601A')->id_ambiente]);

        $queriesWithOne = $this->countSelectorQueries();

        foreach ([8, 9, 10, 11] as $days) {
            $this->createExam(
                ['estado' => Exam::PROGRAMADO, 'nombre_examen' => "Examen {$days}", 'fecha' => $this->futureDate($days)],
                [$this->makeClassroom("70{$days}A")->id_ambiente, $this->makeClassroom("80{$days}B")->id_ambiente]
            );
        }

        $this->assertSame($queriesWithOne, $this->countSelectorQueries());
    }

    private function countSelectorQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/examenes/habilitados')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function makeClassroom(string $number): Classroom
    {
        return Classroom::create([
            'nro_aula' => $number,
            'capacidad' => 60,
            'ubicacion' => 'Edificio Nuevo',
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}
