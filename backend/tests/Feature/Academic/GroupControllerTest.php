<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * HU-18 (registrar grupos) y HU-19 (actualizar grupos).
 *
 * num_grupo es varchar(5): los valores de prueba nunca superan 5 caracteres.
 */
class GroupControllerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /** Crea un grupo del docente en el par y período indicados. */
    private function seedOwnGroup(
        int $careerId,
        int $subjectId,
        string $numGrupo,
        ?int $periodId = null
    ): Group {
        return Group::create([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => $numGrupo,
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $periodId ?? $this->periodoActivoId,
        ]);
    }

    /* ---------------------------- HU-18: registrar ---------------------------- */

    public function test_registra_un_grupo_dentro_del_par_materia_carrera(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.grupo.num_grupo', 'NUEVO');
        $response->assertJsonPath('mensaje', 'Grupo registrado correctamente.');

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
            'gestion' => '2026',
            'id_periodo' => $this->periodoActivoId,
            'id_usuario_docente' => $this->docenteId,
        ]);
    }

    public function test_rechaza_un_grupo_duplicado_en_el_mismo_par_gestion_y_periodo(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
    }

    /**
     * La duplicidad es la quíntupla completa: el mismo número en la misma materia
     * pero en otra carrera es válido. Calculo II existe en Sistemas e Informática.
     */
    public function test_permite_el_mismo_numero_de_grupo_en_otra_carrera(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    public function test_rechaza_el_registro_sin_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/grupos', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id_carrera', 'id_materia', 'num_grupo']);
    }

    public function test_rechaza_un_numero_de_grupo_de_mas_de_cinco_caracteres(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'EXCESO',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
    }

    public function test_rechaza_el_registro_en_un_par_materia_carrera_inexistente(): void
    {
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => 9999,
            'id_materia' => 9999,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(404);
    }

    public function test_rechaza_el_registro_cuando_la_materia_esta_inactiva(): void
    {
        // Par ACTIVO, pero materia.estado = INACTIVO.
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->materiaInactivaId,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_materia');
        $this->assertDatabaseMissing('grupo', [
            'id_materia' => $this->materiaInactivaId,
            'num_grupo' => 'A',
        ]);
    }

    public function test_rechaza_el_registro_cuando_el_par_esta_inactivo(): void
    {
        // Materia ACTIVA, pero materia_carrera.estado = INACTIVO en Informática.
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_materia');
        $this->assertDatabaseMissing('grupo', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ]);
    }

    /**
     * No se exige ningún grupo previo: registrar el primero es lo que incorpora
     * la materia a "Mis materias".
     */
    public function test_un_docente_sin_grupos_previos_puede_crear_el_primero_en_un_par_activo(): void
    {
        $this->assertSame(
            0,
            Group::query()
                ->where('id_carrera', $this->sistemasId)
                ->where('id_materia', $this->basesDatosId)
                ->where('id_usuario_docente', $this->docenteId)
                ->count()
        );
        $antes = $this->getJson('/api/materias')->json('meta.total_mias');

        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
            'id_usuario_docente' => $this->docenteId,
        ]);
        $this->assertSame($antes + 1, $this->getJson('/api/materias')->json('meta.total_mias'));
    }

    public function test_asigna_el_periodo_activo_por_defecto(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'SP',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'num_grupo' => 'SP',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    public function test_permite_verificar_y_elegir_otro_periodo_al_registrar(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'PA',
            'id_periodo' => $this->periodoAnteriorId,
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'num_grupo' => 'PA',
            'id_periodo' => $this->periodoAnteriorId,
            'gestion' => '2025',
        ]);
    }

    /* --------------------------- HU-19: actualizar --------------------------- */

    public function test_actualiza_un_grupo_existente(): void
    {
        $response = $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => 'REN',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.grupo.num_grupo', 'REN');
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'num_grupo' => 'REN',
        ]);
    }

    public function test_actualizar_sin_cambios_no_genera_falso_positivo_de_duplicidad(): void
    {
        // El grupo propio "1" se actualiza a "1": no debe fallar por duplicidad.
        $response = $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => '1',
        ]);

        $response->assertStatus(200);
    }

    public function test_rechaza_la_actualizacion_de_un_grupo_de_otro_docente(): void
    {
        $response = $this->putJson("/api/grupos/{$this->grupoAjenoId}", [
            'num_grupo' => 'B',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoAjenoId,
            'num_grupo' => '3',
        ]);
    }

    public function test_rechaza_actualizar_un_grupo_inexistente(): void
    {
        $this->putJson('/api/grupos/999999', ['num_grupo' => 'B'])->assertStatus(404);
    }

    public function test_rechaza_actualizar_a_un_numero_que_ya_usa_otro_grupo_del_par(): void
    {
        // El grupo propio "1" intenta pasar a "2": ya existe en (Sistemas, Cálculo II).
        $response = $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => '2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'num_grupo' => '1',
        ]);
    }

    public function test_rechaza_actualizar_a_un_numero_usado_por_un_grupo_de_otro_docente(): void
    {
        // El "3" es del otro docente, pero la quíntupla es la misma.
        $this->putJson("/api/grupos/{$this->grupoPropioId}", ['num_grupo' => '3'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');
    }

    /**
     * La duplicidad incluye la carrera: el mismo número en otra carrera de la misma
     * materia no choca con el grupo que se actualiza.
     */
    public function test_permite_actualizar_a_un_numero_que_solo_existe_en_otra_carrera(): void
    {
        $this->seedOwnGroup($this->informaticaId, $this->calculoId, 'X');

        $response = $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => 'X',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
            'num_grupo' => 'X',
        ]);
    }

    public function test_rechaza_actualizar_con_el_numero_de_grupo_vacio(): void
    {
        foreach (['', null] as $empty) {
            $this->putJson("/api/grupos/{$this->grupoPropioId}", ['num_grupo' => $empty])
                ->assertStatus(422)
                ->assertJsonValidationErrors('num_grupo');
        }

        $this->putJson("/api/grupos/{$this->grupoPropioId}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'num_grupo' => '1',
        ]);
    }

    public function test_ignora_intentos_de_cambiar_la_materia_de_origen(): void
    {
        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'id_carrera' => $this->informaticaId,
            'num_grupo' => '1',
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
        ]);
    }

    public function test_ignora_intentos_de_cambiar_la_materia_y_el_docente(): void
    {
        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'id_materia' => $this->basesDatosId,
            'id_usuario_docente' => $this->otroDocenteId,
            'num_grupo' => 'REN',
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'num_grupo' => 'REN',
        ]);
    }

    public function test_actualizar_el_periodo_recalcula_la_gestion(): void
    {
        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => '1',
            'id_periodo' => $this->periodoAnteriorId,
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_periodo' => $this->periodoAnteriorId,
            'gestion' => '2025',
        ]);
    }

    public function test_actualizar_un_grupo_no_afecta_a_otros_grupos_ni_estudiantes(): void
    {
        $this->enrollStudents($this->grupoPropioId, 3, 1);

        $estudianteId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('estado', RecordStatus::ACTIVE)
            ->value('id_estudiante');

        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => 'REN',
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '2',
            'id_usuario_docente' => $this->docenteId,
        ]);

        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $estudianteId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->assertSame(
            4,
            DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->count()
        );
    }
}
