<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
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

    public function test_devuelve_programados_y_en_curso(): void
    {
        $this->actAsTeacher($this->docenteId);

        $programado = $this->createExam(['estado' => Exam::PROGRAMADO]);
        $enCurso = $this->createExam([
            'estado' => Exam::EN_CURSO,
            'nombre_examen' => 'En curso',
            'fecha' => $this->futureDate(8),
        ]);

        $response = $this->getJson('/api/examenes/habilitados')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = array_column($response->json('data'), 'id_examen');

        $this->assertContains($programado->id_examen, $ids);
        $this->assertContains($enCurso->id_examen, $ids);

        // Orden ascendente por fecha.
        $this->assertSame($programado->id_examen, $ids[0]);
        $this->assertSame($enCurso->id_examen, $ids[1]);
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
}
