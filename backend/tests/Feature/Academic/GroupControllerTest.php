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
            'gestion' => '2026', // derivada de periodo.gestion, no enviada por el cliente
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
        $pair = $this->activePair();
        $period = $this->activePeriod(2026);

        $this->postJson('/api/grupos', [
            'id_carrera' => $pair->id_carrera,
            'id_materia' => $pair->id_materia,
            'num_grupo' => 'A',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', ['id_periodo' => $period->id_periodo]);
    }
}