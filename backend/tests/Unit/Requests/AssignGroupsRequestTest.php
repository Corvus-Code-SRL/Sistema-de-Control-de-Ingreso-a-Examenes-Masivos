<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Exams\AssignGroupsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AssignGroupsRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected int $materiaId;
    protected int $materiaOtraId;
    protected int $grupoValidoId;
    protected int $grupoSinNominaId;
    protected int $grupoOtraMateriaId;
    protected int $grupoOtroDocenteId;
    protected $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Materia Principal
        $this->materiaId = DB::table('materia')->insertGetId([
            'nombre'      => 'Cálculo I',
            'codigo'      => 'MAT-101',
            'descripcion' => 'Materia de prueba',
            'estado'      => 'ACTIVO',
        ], 'id_materia');

        // 2. Otra Materia
        $this->materiaOtraId = DB::table('materia')->insertGetId([
            'nombre'      => 'Álgebra Lineal',
            'codigo'      => 'MAT-102',
            'descripcion' => 'Materia secundaria',
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

        // Vincular materias a carrera en materia_carrera
        DB::table('materia_carrera')->insertOrIgnore([
            'id_carrera' => $carreraId,
            'id_materia' => $this->materiaId,
        ]);
        DB::table('materia_carrera')->insertOrIgnore([
            'id_carrera' => $carreraId,
            'id_materia' => $this->materiaOtraId,
        ]);

        $usuario = DB::table('usuario')->first();
        $this->usuarioId = $usuario ? $usuario->id_usuario : DB::table('usuario')->insertGetId([
            'nombre'           => 'Docente',
            'apellido_paterno' => 'Prueba',
            'correo'           => 'docente_assign@test.com',
            'contrasenia'       => bcrypt('password'),
            'cod_sis'          => '202600099',
            'estado'           => 'ACTIVO',
        ], 'id_usuario');

        $otroDocenteId = DB::table('usuario')->where('id_usuario', '!=', $this->usuarioId)->value('id_usuario')
            ?? DB::table('usuario')->insertGetId([
                'nombre'           => 'Otro',
                'apellido_paterno' => 'Docente',
                'correo'           => 'otro_docente@test.com',
                'contrasenia'       => bcrypt('password'),
                'cod_sis'          => '202600088',
                'estado'           => 'ACTIVO',
            ], 'id_usuario');

        $periodo = DB::table('periodo')->first();
        $periodoId = $periodo ? $periodo->id_periodo : DB::table('periodo')->insertGetId([
            'nombre_periodo' => '1-2026',
            'gestion'        => '2026',
        ], 'id_periodo');

        // 3. Grupo válido con nómina en la materia principal
        $this->grupoValidoId = DB::table('grupo')->insertGetId([
            'id_carrera'         => $carreraId,
            'id_materia'         => $this->materiaId,
            'num_grupo'          => 'G1',
            'gestion'            => '1-2026',
            'estado'             => 'ACTIVO',
            'id_usuario_docente' => $this->usuarioId,
            'id_periodo'         => $periodoId,
        ], 'id_grupo');

        // Insertar estudiante en grupo válido
        $estudianteId = DB::table('estudiante')->insertGetId([
            'cod_sis'          => '202011111',
            'ci'               => '12345678',
            'nombre'           => 'Juan',
            'apellido_paterno' => 'Pérez',
            'estado'           => 'ACTIVO',
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo'          => $this->grupoValidoId,
            'id_estudiante'     => $estudianteId,
            'fecha_inscripcion' => date('Y-m-d'),
            'estado'            => 'ACTIVO',
        ]);

        // 4. Grupo sin nómina en la materia principal
        $this->grupoSinNominaId = DB::table('grupo')->insertGetId([
            'id_carrera'         => $carreraId,
            'id_materia'         => $this->materiaId,
            'num_grupo'          => 'G2',
            'gestion'            => '1-2026',
            'estado'             => 'ACTIVO',
            'id_usuario_docente' => $this->usuarioId,
            'id_periodo'         => $periodoId,
        ], 'id_grupo');

        // 5. Grupo perteneciente a otra materia (con nómina)
        $this->grupoOtraMateriaId = DB::table('grupo')->insertGetId([
            'id_carrera'         => $carreraId,
            'id_materia'         => $this->materiaOtraId,
            'num_grupo'          => 'G3',
            'gestion'            => '1-2026',
            'estado'             => 'ACTIVO',
            'id_usuario_docente' => $this->usuarioId,
            'id_periodo'         => $periodoId,
        ], 'id_grupo');

        DB::table('grupo_estudiante')->insert([
            'id_grupo'          => $this->grupoOtraMateriaId,
            'id_estudiante'     => $estudianteId,
            'fecha_inscripcion' => date('Y-m-d'),
            'estado'            => 'ACTIVO',
        ]);

        // 6. Grupo perteneciente a otro docente
        $this->grupoOtroDocenteId = DB::table('grupo')->insertGetId([
            'id_carrera'         => $carreraId,
            'id_materia'         => $this->materiaId,
            'num_grupo'          => 'G4',
            'gestion'            => '1-2026',
            'estado'             => 'ACTIVO',
            'id_usuario_docente' => $otroDocenteId,
            'id_periodo'         => $periodoId,
        ], 'id_grupo');

        DB::table('grupo_estudiante')->insert([
            'id_grupo'          => $this->grupoOtroDocenteId,
            'id_estudiante'     => $estudianteId,
            'fecha_inscripcion' => date('Y-m-d'),
            'estado'            => 'ACTIVO',
        ]);
    }

    private function validate(array $data, array $inputs = []): \Illuminate\Validation\Validator
    {
        $user = User::find($this->usuarioId) ?? new User(['id_usuario' => $this->usuarioId]);
        $this->be($user);

        $request = new AssignGroupsRequest();
        $request->setUserResolver(fn () => $user);
        $request->merge(array_merge($data, $inputs));

        $validator = Validator::make($request->all(), $request->rules(), $request->messages(), $request->attributes());
        $request->withValidator($validator);

        return $validator;
    }

    /** @test */
    public function valida_correctamente_payload_valido_con_grupo_de_la_misma_materia_y_con_nomina()
    {
        $payload = [
            'grupos'     => [$this->grupoValidoId],
            'id_materia' => $this->materiaId,
        ];

        $validator = $this->validate($payload);

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function falla_cuando_la_lista_de_grupos_esta_vacia()
    {
        $validator = $this->validate(['grupos' => []]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('grupos'));
    }

    /** @test */
    public function falla_cuando_un_grupo_no_pertenece_al_docente_actual()
    {
        $payload = [
            'grupos'     => [$this->grupoOtroDocenteId],
            'id_materia' => $this->materiaId,
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('no pertenece al docente', $validator->errors()->first('grupos'));
    }

    /** @test */
    public function falla_cuando_un_grupo_no_pertenece_a_la_materia_del_examen()
    {
        $payload = [
            'grupos'     => [$this->grupoOtraMateriaId],
            'id_materia' => $this->materiaId,
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('no pertenece a la materia', $validator->errors()->first('grupos'));
    }

    /** @test */
    public function falla_cuando_un_grupo_no_tiene_nomina_cargada()
    {
        $payload = [
            'grupos'     => [$this->grupoSinNominaId],
            'id_materia' => $this->materiaId,
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('no tiene una nómina', $validator->errors()->first('grupos'));
    }

    /** @test */
    public function falla_cuando_el_grupo_no_existe()
    {
        $payload = [
            'grupos'     => [999999],
            'id_materia' => $this->materiaId,
        ];

        $validator = $this->validate($payload);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('grupos.0'));
    }

    /** @test */
    public function autorizacion_retorna_true()
    {
        $request = new AssignGroupsRequest();
        $this->assertTrue($request->authorize());
    }
}
