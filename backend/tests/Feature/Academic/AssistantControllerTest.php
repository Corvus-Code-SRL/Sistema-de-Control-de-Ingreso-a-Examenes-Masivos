<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class AssistantControllerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    private string $auxiliarAId;
    private string $auxiliarBId;
    private int $examId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();

        // Rol Auxiliar
        $roleAuxiliar = Role::firstOrCreate(
            ['nombre_rol' => Role::AUXILIAR],
            ['descripcion' => 'Auxiliar de docencia', 'estado' => RecordStatus::ACTIVE]
        );

        // Auxiliar A
        $auxA = User::create([
            'nombre' => 'Ana',
            'apellido_paterno' => 'Auxiliar',
            'correo' => 'ana.aux@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400001',
            'estado' => RecordStatus::ACTIVE,
        ]);
        UserRole::create([
            'id_usuario' => $auxA->id_usuario,
            'id_rol' => $roleAuxiliar->id_rol,
            'fecha_inicio' => now(),
        ]);
        $this->auxiliarAId = $auxA->id_usuario;

        // Auxiliar B
        $auxB = User::create([
            'nombre' => 'Bruno',
            'apellido_paterno' => 'Auxiliar',
            'correo' => 'bruno.aux@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400002',
            'estado' => RecordStatus::ACTIVE,
        ]);
        UserRole::create([
            'id_usuario' => $auxB->id_usuario,
            'id_rol' => $roleAuxiliar->id_rol,
            'fecha_inicio' => now(),
        ]);
        $this->auxiliarBId = $auxB->id_usuario;

        // Tipo de examen (puede ya existir por ExamTypeSeeder)
        $tipoExamenId = DB::table('tipo_examen')->value('id_tipo_examen');

        if ($tipoExamenId === null) {
            $tipoExamenId = DB::table('tipo_examen')->insertGetId([
                'nombre' => 'Parcial',
                'categoria' => 'REGULAR',
            ], 'id_tipo_examen');
        }

        // Examen del docente en el par Sistemas + Cálculo II
        $exam = Exam::create([
            'nombre_examen' => 'Parcial 1',
            'fecha' => '2026-10-15',
            'hora_inicio' => '08:00',
            'id_tipo_examen' => $tipoExamenId,
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'estado' => Exam::PROGRAMADO,
        ]);

        $this->examId = (int) $exam->id_examen;

        // Vincular el grupo propio al examen
        DB::table('grupo_examen')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_examen' => $this->examId,
        ]);
    }

    public function test_flujo_completo_anadir_habilitar_y_quitar(): void
    {
        // 1. Añadir 2 auxiliares al grupo
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ])->assertStatus(201);

        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarBId,
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo_auxiliar', [
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarAId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('grupo_auxiliar', [
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarBId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        // 2. Habilitar 1 para el examen
        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ])->assertStatus(201);

        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarAId,
        ]);

        // 3. Quitar 1 del grupo (soft delete)
        $this->deleteJson(
            "/api/docente/grupos/{$this->grupoPropioId}/auxiliares/{$this->auxiliarBId}"
        )->assertStatus(200);

        $this->assertDatabaseHas('grupo_auxiliar', [
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarBId,
            'estado' => RecordStatus::INACTIVE,
        ]);

        // El auxiliar A sigue activo
        $this->assertDatabaseHas('grupo_auxiliar', [
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarAId,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_rechaza_habilitar_a_un_estudiante_del_mismo_examen(): void
    {
        // Crear un estudiante y registrarlo en el examen
        $estudianteId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '202400003',
            'ci' => '99999999',
            'nombre' => 'Carlos',
            'apellido_paterno' => 'Estudiante',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $estudianteId,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);

        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->examId,
            'id_estudiante' => $estudianteId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'id_grupo' => $this->grupoPropioId,
        ]);

        // Crear un auxiliar con el mismo cod_sis del estudiante
        $roleAuxiliar = Role::where('nombre_rol', Role::AUXILIAR)->firstOrFail();

        $aux = User::create([
            'nombre' => 'Carlos',
            'apellido_paterno' => 'Estudiante',
            'correo' => 'carlos.aux@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400003',   // mismo SIS que el estudiante
            'estado' => RecordStatus::ACTIVE,
        ]);
        UserRole::create([
            'id_usuario' => $aux->id_usuario,
            'id_rol' => $roleAuxiliar->id_rol,
            'fecha_inicio' => now(),
        ]);

        // Añadirlo al grupo
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $aux->id_usuario,
        ])->assertStatus(201);

        // Intentar habilitarlo para el examen: debe fallar
        $response = $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $aux->id_usuario,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_usuario');
    }

    public function test_rechaza_anadir_un_usuario_sin_rol_auxiliar(): void
    {
        $noAuxiliar = User::create([
            'nombre' => 'Pedro',
            'apellido_paterno' => 'Docente',
            'correo' => 'pedro.doc@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202400099',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $response = $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $noAuxiliar->id_usuario,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_usuario');
    }

    public function test_buscar_auxiliar_por_sis_y_por_nombre(): void
    {
        $response = $this->getJson('/api/docente/auxiliares/buscar?criterio=202400001');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.cod_sis', '202400001');

        $response = $this->getJson('/api/docente/auxiliares/buscar?criterio=Ana');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_rechaza_habilitar_auxiliar_si_el_examen_no_esta_programado(): void
    {
        // Cambia el examen a EN_INGRESO
        DB::table('examen')->where('id_examen', $this->examId)->update(['estado' => 'EN_INGRESO']);

        $response = $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_examen');
    }

    public function test_rechaza_quitar_auxiliar_si_el_examen_no_esta_programado(): void
    {
        // 1. Primero hay que incorporar al auxiliar al grupo vinculado al examen.
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ])->assertStatus(201);

        // 2. Ahora sí, habilitarlo para el examen.
        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ])->assertStatus(201);

        // 3. Cambiar el examen a FINALIZADO.
        DB::table('examen')->where('id_examen', $this->examId)->update(['estado' => 'FINALIZADO']);

        // 4. Intentar quitar: debe fallar con 422.
        $response = $this->deleteJson(
            "/api/docente/examenes/{$this->examId}/auxiliares/{$this->auxiliarAId}"
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_examen');
    }
}