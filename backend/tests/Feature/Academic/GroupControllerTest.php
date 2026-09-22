<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /**
     * Crea un grupo del docente en el par y período indicados.
     * Necesario para CA7: el docente debe tener un grupo previo en el par.
     */
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

    public function test_registra_un_grupo_dentro_del_par_materia_carrera(): void
    {
        // El trait ya cargó 2 grupos propios en (Sistemas, Cálculo II).
        // CA7 se cumple porque el docente ya dicta en este par.
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.grupo.num_grupo', 'NUEVO');

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
            'gestion' => '2026',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    public function test_rechaza_un_grupo_duplicado_en_el_mismo_par_gestion_y_periodo(): void
    {
        // El trait ya creó grupo "1" para el docente en (Sistemas, Cálculo II).
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',   // ya existe
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
    }

    /**
     * CA 3 — el mismo número de grupo en la misma materia pero en otra carrera
     * es válido. El trait tiene Cálculo II en Sistemas y en Informática.
     */
    public function test_permite_el_mismo_numero_de_grupo_en_otra_carrera(): void
    {
        // En Informática + Cálculo II el docente NO tiene grupos. Creamos uno
        // para pasar CA7 (porque CA7 exige al menos un grupo previo en el par).
        $this->seedOwnGroup($this->informaticaId, $this->calculoId, 'Z');

        // Ahora registramos el "1" en Informática: ya existe "1" en Sistemas
        // pero en otra carrera, así que NO es duplicidad.
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
        ]);

        $response->assertStatus(201);
    }

    public function test_rechaza_el_registro_sin_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/grupos', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id_carrera', 'id_materia', 'num_grupo']);
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

    public function test_asigna_el_periodo_activo_por_defecto(): void
    {
        // El trait ya tiene grupos propios en (Sistemas, Cálculo II): CA7 OK.
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'SIN-PERIODO',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'num_grupo' => 'SIN-PERIODO',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    public function test_actualiza_un_grupo_existente(): void
    {
        $response = $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => 'RENOMBRADO',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'num_grupo' => 'RENOMBRADO',
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
        // $this->grupoAjenoId es del otro docente.
        $response = $this->putJson("/api/grupos/{$this->grupoAjenoId}", [
            'num_grupo' => 'B',
        ]);

        $response->assertStatus(403);
    }

    public function test_ignora_intentos_de_cambiar_la_materia_de_origen(): void
    {
        // Se intenta cambiar id_carrera del grupo propio; el backend lo ignora.
        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'id_carrera' => $this->informaticaId,
            'num_grupo' => '1',
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,   // conserva la original
        ]);
    }

    public function test_actualizar_el_periodo_recalcula_la_gestion(): void
    {
        // El trait tiene periodoActivoId (2026) y periodoAnteriorId (2025).
        // Actualizar el período debe recalcular grupo.gestion.
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
        // Inscribimos 3 estudiantes activos y 1 retirado en el grupo propio.
        $this->enrollStudents($this->grupoPropioId, 3, 1);

        // Guardamos el id de un estudiante activo antes de actualizar.
        $estudianteId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('estado', RecordStatus::ACTIVE)
            ->value('id_estudiante');

        // Actualizamos el número de grupo.
        $this->putJson("/api/grupos/{$this->grupoPropioId}", [
            'num_grupo' => 'RENOMBRADO',
        ])->assertStatus(200);

        // El otro grupo propio sigue con su número original.
        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '2',
            'id_usuario_docente' => $this->docenteId,
        ]);

        // El estudiante sigue inscrito y activo.
        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $estudianteId,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_rechaza_registrar_grupo_en_materia_que_no_le_corresponde(): void
    {
        // (Sistemas, Bases de Datos I): el docente NO tiene grupos.
        // El trait creó el par activo pero con OTRO docente.
        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'NUEVO',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_materia');
    }
}