<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Exams\CreateExamRequest;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CreateExamRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected int $materiaId;
    protected int $ambienteId;
    protected int $grupoId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Obtener o crear registros requeridos para pruebas de existencia (exists)
        $materia = DB::table('materia')->first();
        if (!$materia) {
            $this->materiaId = DB::table('materia')->insertGetId([
                'nombre'      => 'Materia de Prueba',
                'codigo'      => 'MAT-101',
                'descripcion' => 'Descripción de prueba',
                'estado'      => 'ACTIVO',
            ], 'id_materia');
        } else {
            $this->materiaId = $materia->id_materia;
        }

        $classroom = Classroom::first();
        if (!$classroom) {
            $classroom = Classroom::create([
                'nro_aula'  => 'Aula 101',
                'capacidad' => 50,
                'ubicacion' => 'Bloque A',
                'estado'    => 'ACTIVO',
            ]);
        }
        $this->ambienteId = $classroom->id_ambiente;

        $carrera = DB::table('carrera')->first();
        if (!$carrera) {
            $facultad = DB::table('facultad')->first();
            $facultadId = $facultad ? $facultad->id_facultad : DB::table('facultad')->insertGetId([
                'nombre' => 'Facultad de Ciencias y Tecnología',
                'codigo' => 'FCYT',
                'estado' => 'ACTIVO',
            ], 'id_facultad');

            $carreraId = DB::table('carrera')->insertGetId([
                'nombre'      => 'Ingeniería Informática',
                'codigo'      => 'INF',
                'estado'      => 'ACTIVO',
                'id_facultad' => $facultadId,
            ], 'id_carrera');
        } else {
            $carreraId = $carrera->id_carrera;
        }

        DB::table('materia_carrera')->insertOrIgnore([
            'id_carrera' => $carreraId,
            'id_materia' => $this->materiaId,
        ]);

        $usuario = DB::table('usuario')->first();
        $usuarioId = $usuario ? $usuario->id_usuario : DB::table('usuario')->insertGetId([
            'nombre'           => 'Docente',
            'apellido_paterno' => 'Prueba',
            'correo'           => 'docente@test.com',
            'contrasenia'       => bcrypt('password'),
            'cod_sis'          => '202600001',
            'estado'           => 'ACTIVO',
        ], 'id_usuario');

        $periodo = DB::table('periodo')->first();
        $periodoId = $periodo ? $periodo->id_periodo : DB::table('periodo')->insertGetId([
            'nombre_periodo' => '1-2026',
            'gestion'        => '2026',
        ], 'id_periodo');

        $grupo = DB::table('grupo')->first();
        if (!$grupo) {
            $this->grupoId = DB::table('grupo')->insertGetId([
                'id_carrera'         => $carreraId,
                'id_materia'         => $this->materiaId,
                'num_grupo'          => '1',
                'gestion'            => '1-2026',
                'estado'             => 'ACTIVO',
                'id_usuario_docente' => $usuarioId,
                'id_periodo'         => $periodoId,
            ], 'id_grupo');
        } else {
            $this->grupoId = $grupo->id_grupo;
        }
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new CreateExamRequest();
        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }

    /** @test */
    public function valida_correctamente_un_payload_valido_de_creacion_de_examen()
    {
        $payload = [
            'nombre_examen' => 'Primer Examen Parcial',
            'id_materia'    => $this->materiaId,
            'fecha'         => date('Y-m-d', strtotime('+1 day')),
            'hora_inicio'   => '10:00',
            'hora_fin'      => '12:00',
            'duracion'      => 120,
            'ambientes'     => [$this->ambienteId],
            'grupos'        => [$this->grupoId],
            'normas'        => 'Uso estricto de carnet universitario.',
            'categoria'     => 'REGULAR',
        ];

        $validator = $this->validate($payload);

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function falla_cuando_los_campos_obligatorios_estan_ausentes()
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();

        $this->assertTrue($errors->has('nombre_examen'));
        $this->assertTrue($errors->has('id_materia'));
        $this->assertTrue($errors->has('fecha'));
        $this->assertTrue($errors->has('hora_inicio'));
        $this->assertTrue($errors->has('duracion'));
        $this->assertTrue($errors->has('ambientes'));
    }

    /** @test */
    public function falla_si_la_materia_o_ambientes_no_existen()
    {
        $payload = [
            'nombre_examen' => 'Examen Inválido',
            'id_materia'    => 999999,
            'fecha'         => date('Y-m-d', strtotime('+1 day')),
            'hora_inicio'   => '08:00',
            'duracion'      => 60,
            'ambientes'     => [999999],
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('id_materia'));
        $this->assertTrue($validator->errors()->has('ambientes.0'));
    }

    /** @test */
    public function falla_si_la_fecha_es_anterior_a_hoy()
    {
        $payload = [
            'nombre_examen' => 'Examen Pasado',
            'id_materia'    => $this->materiaId,
            'fecha'         => '2020-01-01',
            'hora_inicio'   => '08:00',
            'duracion'      => 60,
            'ambientes'     => [$this->ambienteId],
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('fecha'));
    }

    /** @test */
    public function falla_si_la_categoria_no_es_valida()
    {
        $payload = [
            'nombre_examen' => 'Examen Categoría Rara',
            'id_materia'    => $this->materiaId,
            'fecha'         => date('Y-m-d', strtotime('+1 day')),
            'hora_inicio'   => '08:00',
            'duracion'      => 60,
            'ambientes'     => [$this->ambienteId],
            'categoria'     => 'INVALIDA',
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('categoria'));
    }

    /** @test */
    public function autorizacion_retorna_true()
    {
        $request = new CreateExamRequest();
        $this->assertTrue($request->authorize());
    }
}
