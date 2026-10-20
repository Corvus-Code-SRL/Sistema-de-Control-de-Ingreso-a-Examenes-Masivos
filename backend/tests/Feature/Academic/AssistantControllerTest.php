<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\Classroom;
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

    public function test_habilita_auxiliar_directamente_sin_pertenecer_a_un_grupo(): void
    {
        // Bruno tiene cuenta activa y rol Auxiliar vigente,
        // pero no pertenece a ningún grupo académico.
        $this->assertDatabaseMissing('grupo_auxiliar', [
            'id_usuario' => $this->auxiliarBId,
        ]);

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarBId,
        ])->assertCreated();

        // Debe quedar habilitado directamente para el examen.
        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarBId,
        ]);

        // La habilitación no debe crear una vinculación con grupos.
        $this->assertDatabaseMissing('grupo_auxiliar', [
            'id_usuario' => $this->auxiliarBId,
        ]);

        // El auxiliar habilitado directamente debe aparecer
        // en el listado del examen, aunque no pertenezca a grupos.
        $this->getJson("/api/examenes/{$this->examId}/auxiliares")
            ->assertOk()
            ->assertJsonPath('data.editable', true)
            ->assertJsonFragment([
                'id_usuario' => $this->auxiliarBId,
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

        // Con el examen PROGRAMADO no hay ingresos: el estudiante solo es "esperado" por su grupo.
        $this->assertSame(0, DB::table('examen_estudiante')->where('id_examen', $this->examId)->count());

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

    public function test_search_matches_accents_case_sis_and_full_name(): void
    {
        User::whereKey($this->auxiliarAId)->update([
            'nombre' => 'Iván',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'Gómez',
        ]);

        foreach (['Ivan', 'IVAN', 'perez', 'GOMEZ', 'ivan perez gomez', '202400001'] as $criteria) {
            $this->getJson('/api/docente/auxiliares/buscar?' . http_build_query(['criterio' => $criteria]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id_usuario', $this->auxiliarAId);
        }
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        foreach (['%', '_'] as $character) {
            User::whereKey($this->auxiliarAId)->update(['nombre' => 'Ana' . $character . 'Literal']);
            User::whereKey($this->auxiliarBId)->update(['nombre' => 'AnaXLiteral']);

            $this->getJson('/api/docente/auxiliares/buscar?' . http_build_query([
                'criterio' => 'Ana' . $character,
            ]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id_usuario', $this->auxiliarAId);
        }
    }

    public function test_search_excludes_inactive_users_and_expired_roles(): void
    {
        User::whereKey($this->auxiliarAId)->update(['estado' => RecordStatus::INACTIVE]);
        UserRole::where('id_usuario', $this->auxiliarBId)->update(['fecha_fin' => now()]);

        $this->getJson('/api/docente/auxiliares/buscar?criterio=20240000')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_assistant_routes_reject_out_of_range_identifiers(): void
    {
        foreach (['1000000000', '2147483648', '999999999999999999999999', '-1'] as $identifier) {
            foreach (['grupos', 'examenes'] as $resource) {
                $url = "/api/docente/{$resource}/{$identifier}/auxiliares";

                $this->postJson($url, ['id_usuario' => $this->auxiliarAId])->assertNotFound();
                $this->deleteJson($url . '/' . $this->auxiliarAId)->assertNotFound();
            }
        }
    }

    public function test_assistant_removal_routes_reject_invalid_user_uuids(): void
    {
        $this->deleteJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares/not-a-uuid")
            ->assertNotFound();
        $this->deleteJson("/api/docente/examenes/{$this->examId}/auxiliares/not-a-uuid")
            ->assertNotFound();
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

    public function test_las_respuestas_llevan_data_y_mensaje(): void
    {
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarAId,
        ])
            ->assertCreated()
            ->assertJsonPath('mensaje', 'Auxiliar incorporado al grupo correctamente.')
            ->assertJsonPath('data', null);

        $this->getJson('/api/docente/auxiliares')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_usuario', $this->auxiliarAId)
            ->assertJsonPath('data.0.grupos.0.id_grupo', $this->grupoPropioId)
            ->assertJsonPath('data.0.examenes_disponibles.0.id_examen', $this->examId);

        $this->getJson('/api/docente/grupos')
            ->assertOk()
            ->assertJsonPath('data.0.id_grupo', $this->grupoPropioId);

        $this->deleteJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares/{$this->auxiliarAId}")
            ->assertOk()
            ->assertJsonPath('mensaje', 'Auxiliar quitado del grupo correctamente.');
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

    public function test_sugiere_auxiliares_vinculados_a_los_grupos_del_examen(): void
    {
        // Bruno pertenece al grupo vinculado al examen.
        DB::table('grupo_auxiliar')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarBId,
            'fecha_incorporacion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);

        // Ana pertenece a un grupo ajeno al examen.
        DB::table('grupo_auxiliar')->insert([
            'id_grupo' => $this->grupoAjenoId,
            'id_usuario' => $this->auxiliarAId,
            'fecha_incorporacion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/docente/examenes/{$this->examId}/auxiliares/sugeridos"
        );

        $response->assertOk();

        $suggestions = collect($response->json('data'));

        // Bruno debe aparecer como sugerido.
        $this->assertTrue(
            $suggestions->contains('id_usuario', $this->auxiliarBId)
        );

        // Ana no debe aparecer porque su grupo no pertenece al examen.
        $this->assertFalse(
            $suggestions->contains('id_usuario', $this->auxiliarAId)
        );

        // Mostrar una sugerencia no habilita automáticamente al auxiliar.
        $this->assertDatabaseMissing('examen_auxiliar', [
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarBId,
        ]);
    }

    public function test_no_permite_consultar_sugerencias_de_un_examen_ajeno(): void
    {
        // El examen pasa a pertenecer a otro docente.
        DB::table('examen')
            ->where('id_examen', $this->examId)
            ->update([
                'id_usuario_docente' => $this->otroDocenteId,
            ]);

        // El docente autenticado no debe acceder a sus sugerencias.
        $this->getJson(
            "/api/docente/examenes/{$this->examId}/auxiliares/sugeridos"
        )->assertForbidden();
    }

    public function test_sugerencias_excluyen_usuarios_inactivos_y_roles_vencidos(): void
    {
        // Ambos auxiliares pertenecen al grupo del examen.
        foreach ([$this->auxiliarAId, $this->auxiliarBId] as $assistantId) {
            DB::table('grupo_auxiliar')->insert([
                'id_grupo' => $this->grupoPropioId,
                'id_usuario' => $assistantId,
                'fecha_incorporacion' => now()->toDateString(),
                'estado' => RecordStatus::ACTIVE,
            ]);
        }

        // Ana tiene la cuenta inactiva.
        User::whereKey($this->auxiliarAId)
            ->update(['estado' => RecordStatus::INACTIVE]);

        $url = "/api/docente/examenes/{$this->examId}/auxiliares/sugeridos";

        $suggestions = collect(
            $this->getJson($url)->assertOk()->json('data')
        );

        // Ana no aparece, pero Bruno sí.
        $this->assertFalse(
            $suggestions->contains('id_usuario', $this->auxiliarAId)
        );

        $this->assertTrue(
            $suggestions->contains('id_usuario', $this->auxiliarBId)
        );

        // El rol Auxiliar de Bruno deja de estar vigente.
        UserRole::where('id_usuario', $this->auxiliarBId)
            ->update(['fecha_fin' => now()]);

        // Ninguno de los dos debe aparecer.
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sugerencia_identifica_auxiliar_ya_habilitado(): void
    {
        DB::table('grupo_auxiliar')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_usuario' => $this->auxiliarBId,
            'fecha_incorporacion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);

        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarBId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'fecha_habilitacion' => now(),
            'id_ambiente' => null,
        ]);

        $response = $this->getJson(
            "/api/docente/examenes/{$this->examId}/auxiliares/sugeridos"
        )->assertOk();

        $suggestions = collect($response->json('data'));

        $this->assertCount(1, $suggestions);
        $this->assertSame($this->auxiliarBId, $suggestions[0]['id_usuario']);
        $this->assertTrue($suggestions[0]['habilitado']);
    }

    public function test_habilitar_auxiliar_valida_aulas_de_examenes_simultaneos(): void
    {
        $aulaA = Classroom::create([
            'nro_aula' => 'QA-691A',
            'capacidad' => 60,
            'ubicacion' => 'Edificio Nuevo',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $aulaB = Classroom::create([
            'nro_aula' => 'QA-692B',
            'capacidad' => 60,
            'ubicacion' => 'Edificio Nuevo',
            'estado' => RecordStatus::ACTIVE,
        ]);

        // Examen que el docente desea habilitar.
        $exam = Exam::findOrFail($this->examId);
        $exam->update([
            'duracion' => 90,
            'hora_fin' => '09:30',
        ]);
        $exam->classrooms()->attach($aulaA->id_ambiente);

        // Examen propio simultáneo en un ambiente diferente.
        $otherExam = Exam::create([
            'nombre_examen' => 'Otro parcial simultáneo',
            'fecha' => $exam->fecha->toDateString(),
            'hora_inicio' => '08:30',
            'hora_fin' => '10:00',
            'duracion' => 90,
            'id_tipo_examen' => $exam->id_tipo_examen,
            'id_carrera' => $exam->id_carrera,
            'id_materia' => $exam->id_materia,
            'id_usuario_docente' => $this->docenteId,
            'estado' => Exam::PROGRAMADO,
        ]);

        $otherExam->classrooms()->attach($aulaB->id_ambiente);

        // Bruno ya está habilitado para el examen del aula B.
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $otherExam->id_examen,
            'id_usuario' => $this->auxiliarBId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'fecha_habilitacion' => now(),
            'id_ambiente' => null,
        ]);

        // No puede habilitarse simultáneamente en el aula A.
        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarBId,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_usuario');

        $this->assertDatabaseMissing('examen_auxiliar', [
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarBId,
        ]);

        // Si ambos exámenes utilizan el aula A, sí es posible.
        $otherExam->classrooms()->sync([$aulaA->id_ambiente]);

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarBId,
        ])->assertCreated();

        $this->assertDatabaseHas('examen_auxiliar', [
            'id_examen' => $this->examId,
            'id_usuario' => $this->auxiliarBId,
        ]);
    }
}
