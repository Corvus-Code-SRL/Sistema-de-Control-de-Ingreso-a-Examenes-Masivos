<?php

namespace Tests\Feature\Academic;

use App\Models\Career;
use App\Models\Group;
use App\Models\Period;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function activePair(): SubjectCareer
    {
        $career = Career::factory()->create(['estado' => RecordStatus::ACTIVE]);
        $subject = Subject::factory()->create(['estado' => RecordStatus::ACTIVE]);

        return SubjectCareer::create([
            'id_carrera' => $career->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    /** El período trae su propia gestión (smallint): grupo.gestion se deriva de ahí. */
    private function activePeriod(int $management = 2026): Period
    {
        $period = Period::factory()->create(['gestion' => $management]);

        config(['sciem.periodo_activo_id' => $period->id_periodo]);
        config(['sciem.docente_fijo_id' => (string) Str::uuid()]);

        return $period;
    }

    public function test_registra_un_grupo_dentro_del_par_materia_carrera(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        // El docente ya dicta un grupo en este par: CA7 le permite registrar más.
        Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'Z',   // número distinto al que se va a registrar
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.grupo.num_grupo', 'A');

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => '2026',
            'id_periodo' => $period->id_periodo,
        ]);
    }
    public function test_rechaza_un_grupo_duplicado_en_el_mismo_par_gestion_y_periodo(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
    }

    /**
     * CA 3 — el mismo número de grupo, en la misma materia pero en otra carrera,
     * es un registro legítimo y NO debe bloquearse.
     */
    public function test_permite_el_mismo_numero_de_grupo_en_otra_carrera(): void
    {
        $subject = Subject::factory()->create(['estado' => RecordStatus::ACTIVE]);
        $careerA = Career::factory()->create(['estado' => RecordStatus::ACTIVE]);
        $careerB = Career::factory()->create(['estado' => RecordStatus::ACTIVE]);

        SubjectCareer::create([
            'id_carrera' => $careerA->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        SubjectCareer::create([
            'id_carrera' => $careerB->id_carrera,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $period = $this->activePeriod(2026);

        Group::create([
            'id_carrera' => $careerA->id_carrera,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $careerB->id_carrera,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
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
        $this->activePeriod();

        $response = $this->postJson('/api/grupos', [
            'id_carrera' => 9999,
            'id_materia' => 9999,
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(404);
    }

    public function test_asigna_el_periodo_activo_por_defecto(): void
    {
        Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'Z',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]); 
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $this->postJson('/api/grupos', [
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', ['id_periodo' => $period->id_periodo]);
    }

    public function test_actualiza_un_grupo_existente(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->putJson("/api/grupos/{$group->id_grupo}", [
            'num_grupo' => 'B',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('grupo', ['id_grupo' => $group->id_grupo, 'num_grupo' => 'B']);
    }

    /**
     * La duplicidad se verifica excluyendo el propio registro:
     * actualizar un grupo sin cambiar su identificación no debe fallar.
     */
    public function test_actualizar_sin_cambios_no_genera_falso_positivo_de_duplicidad(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->putJson("/api/grupos/{$group->id_grupo}", [
            'num_grupo' => 'A',
        ]);

        $response->assertStatus(200);
    }

    public function test_rechaza_la_actualizacion_de_un_grupo_de_otro_docente(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => (string) Str::uuid(), // otro docente, no el de config()
            'id_periodo' => $period->id_periodo,
        ]);

        $response = $this->putJson("/api/grupos/{$group->id_grupo}", [
            'num_grupo' => 'B',
        ]);

        $response->assertStatus(403);
    }

    public function test_ignora_intentos_de_cambiar_la_materia_de_origen(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);
        $otherCareer = Career::factory()->create(['estado' => RecordStatus::ACTIVE]);

        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $this->putJson("/api/grupos/{$group->id_grupo}", [
            'id_carrera' => $otherCareer->id_carrera, // se intenta cambiar; debe ignorarse
            'num_grupo' => 'A',
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $group->id_grupo,
            'id_carrera' => $pair->id_carrera, // conserva la original
        ]);
    }

    public function test_actualizar_el_periodo_recalcula_la_gestion(): void
    {
        $pair = $this->activePair();
        $period2026 = $this->activePeriod(2026);
        $period2027 = Period::factory()->create(['gestion' => 2027]);

        $group = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period2026->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period2026->id_periodo,
        ]);

        $this->putJson("/api/grupos/{$group->id_grupo}", [
            'num_grupo' => 'A',
            'id_periodo' => $period2027->id_periodo,
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $group->id_grupo,
            'id_periodo' => $period2027->id_periodo,
            'gestion' => '2027',
        ]);
    }

    public function test_actualizar_un_grupo_no_afecta_a_otros_grupos_ni_estudiantes(): void
    {
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $groupA = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        $groupB = Group::create([
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'B',
            'gestion' => (string) $period->gestion,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => config('sciem.docente_fijo_id'),
            'id_periodo' => $period->id_periodo,
        ]);

        // Estudiante inscrito en groupA.
        $estudiante = \App\Models\Estudiante::factory()->create(['estado' => RecordStatus::ACTIVE]);
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupA->id_grupo,
            'id_estudiante' => $estudiante->id_estudiante,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->putJson("/api/grupos/{$groupA->id_grupo}", [
            'num_grupo' => 'A2',
        ])->assertStatus(200);

        // groupB intacto.
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $groupB->id_grupo,
            'num_grupo' => 'B',
        ]);

        // La inscripción sigue.
        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $groupA->id_grupo,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}