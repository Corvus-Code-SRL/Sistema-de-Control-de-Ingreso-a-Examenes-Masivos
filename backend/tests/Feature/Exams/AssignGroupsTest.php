<?php

namespace Tests\Feature\Exams;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignGroupsTest extends TestCase
{
    use DatabaseTransactions;

    protected int $materiaId;
    protected int $grupoId;
    protected int $estudianteId;
    protected int $examenId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Materia
        $this->materiaId = DB::table('materia')->insertGetId([
            'nombre'      => 'Física I',
            'codigo'      => 'FIS-101',
            'descripcion' => 'Física General',
            'estado'      => 'ACTIVO',
        ], 'id_materia');

        $facultad = DB::table('facultad')->first();
        $facultadId = $facultad ? $facultad->id_facultad : DB::table('facultad')->insertGetId([
            'nombre' => 'Facultad de Ciencias y Tecnología',
            'codigo' => 'FCYT',
            'estado' => 'ACTIVO',
        ], 'id_facultad');

        $carrera = DB::table('carrera')->first();
        $carreraId = $carrera ? $carrera->id_carrera : DB::table('carrera')->insertGetId([
            'nombre'      => 'Ingeniería Informática',
            'codigo'      => 'INF',
            'estado'      => 'ACTIVO',
            'id_facultad' => $facultadId,
        ], 'id_carrera');

        DB::table('materia_carrera')->insertOrIgnore([
            'id_carrera' => $carreraId,
            'id_materia' => $this->materiaId,
        ]);

        $usuario = DB::table('usuario')->first();
        $usuarioId = $usuario ? $usuario->id_usuario : DB::table('usuario')->insertGetId([
            'nombre'           => 'Docente',
            'apellido_paterno' => 'Física',
            'correo'           => 'docente_fisica@test.com',
            'contrasenia'       => bcrypt('password'),
            'cod_sis'          => '202600077',
            'estado'           => 'ACTIVO',
        ], 'id_usuario');

        $periodo = DB::table('periodo')->first();
        $periodoId = $periodo ? $periodo->id_periodo : DB::table('periodo')->insertGetId([
            'nombre_periodo' => '1-2026',
            'gestion'        => '2026',
        ], 'id_periodo');

        // Grupo activo de la materia
        $this->grupoId = DB::table('grupo')->insertGetId([
            'id_carrera'         => $carreraId,
            'id_materia'         => $this->materiaId,
            'num_grupo'          => 'G1',
            'gestion'            => '1-2026',
            'estado'             => 'ACTIVO',
            'id_usuario_docente' => $usuarioId,
            'id_periodo'         => $periodoId,
        ], 'id_grupo');

        // Estudiante inscrito en el grupo
        $this->estudianteId = DB::table('estudiante')->insertGetId([
            'cod_sis'          => '202022222',
            'ci'               => '87654321',
            'nombre'           => 'María',
            'apellido_paterno' => 'Gómez',
            'estado'           => 'ACTIVO',
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo'          => $this->grupoId,
            'id_estudiante'     => $this->estudianteId,
            'fecha_inscripcion' => date('Y-m-d'),
            'estado'            => 'ACTIVO',
        ]);

        // Tipo Examen y Examen
        $tipo = ExamType::first() ?? ExamType::create([
            'nombre'    => 'Parcial 1',
            'categoria' => 'REGULAR',
        ]);

        $examen = Exam::create([
            'nombre_examen'  => 'Primer Parcial Física',
            'fecha'          => date('Y-m-d', strtotime('+1 day')),
            'hora_inicio'    => '08:00',
            'hora_fin'       => '10:00',
            'duracion'       => 120,
            'id_tipo_examen' => $tipo->id_tipo_examen,
        ]);
        $this->examenId = $examen->id_examen;
    }

    /** @test */
    public function puede_obtener_grupos_de_una_materia_por_api()
    {
        $response = $this->getJson("/api/exams/materias/{$this->materiaId}/grupos");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => [
                             'id_grupo',
                             'num_grupo',
                             'gestion',
                             'estado',
                             'cantidad_estudiantes',
                             'tiene_nomina',
                         ]
                     ]
                 ]);

        $this->assertEquals($this->grupoId, $response->json('data.0.id_grupo'));
        $this->assertEquals(1, $response->json('data.0.cantidad_estudiantes'));
        $this->assertTrue($response->json('data.0.tiene_nomina'));
    }

    /** @test */
    public function puede_asignar_grupos_a_un_examen_y_poblar_estudiantes()
    {
        $payload = [
            'grupos'     => [$this->grupoId],
            'id_materia' => $this->materiaId,
        ];

        $response = $this->postJson("/api/exams/{$this->examenId}/grupos", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Grupos asignados al examen exitosamente');

        // Verificar vinculación en grupo_examen
        $this->assertDatabaseHas('grupo_examen', [
            'id_examen' => $this->examenId,
            'id_grupo'  => $this->grupoId,
        ]);

        // Verificar vinculación automática en examen_estudiante
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen'           => $this->examenId,
            'id_estudiante'       => $this->estudianteId,
            'id_grupo'            => $this->grupoId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso'      => 'NO_INGRESO',
        ]);
    }
}
