<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-021 (multicarrera): un grupo atiende a varias carreras que ofertan su materia.
 *
 * En el catálogo de prueba Cálculo II se oferta en Sistemas e Informática; el grupo propio es
 * de Sistemas, así que Informática es la carrera que se le puede sumar.
 */
class GroupCareersTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
        $this->seed(ActionSeeder::class);
    }

    public function test_registra_un_grupo_con_una_carrera_adicional(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'MULTI',
            'carreras_adicionales' => [$this->informaticaId],
        ])->assertStatus(201);

        $groupId = (int) DB::table('grupo')->where('num_grupo', 'MULTI')->value('id_grupo');

        $this->assertEqualsCanonicalizing(
            [$this->sistemasId, $this->informaticaId],
            $this->offeringCareers($groupId)
        );
    }

    public function test_sin_carreras_adicionales_el_grupo_atiende_solo_a_su_carrera(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'SOLO',
        ])->assertStatus(201);

        $groupId = (int) DB::table('grupo')->where('num_grupo', 'SOLO')->value('id_grupo');

        $this->assertSame([$this->sistemasId], $this->offeringCareers($groupId));
    }

    public function test_rechaza_una_carrera_que_no_oferta_la_materia(): void
    {
        // Base de Datos I está inactiva en Informática: Informática no la oferta.
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'BD',
            'carreras_adicionales' => [$this->informaticaId],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('carreras_adicionales');

        $this->assertFalse(DB::table('grupo')->where('num_grupo', 'BD')->exists());
    }

    public function test_rechaza_repetir_la_carrera_principal_como_adicional(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'REP',
            'carreras_adicionales' => [$this->sistemasId],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('carreras_adicionales');
    }

    public function test_rechaza_carreras_adicionales_repetidas_o_no_numericas(): void
    {
        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'DUP',
            'carreras_adicionales' => [$this->informaticaId, $this->informaticaId, 'x'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['carreras_adicionales.0', 'carreras_adicionales.2']);
    }

    public function test_el_detalle_muestra_las_carreras_del_grupo_y_las_disponibles(): void
    {
        $response = $this->getJson('/api/grupos/' . $this->grupoPropioId)->assertOk();

        $response->assertJsonPath('meta.carreras.0.id_carrera', $this->sistemasId);
        $response->assertJsonPath('meta.carreras.0.principal', true);
        $response->assertJsonCount(1, 'meta.carreras');
        $response->assertJsonPath('meta.carreras_disponibles.0.id_carrera', $this->informaticaId);
    }

    public function test_la_lista_de_grupos_del_par_ofrece_las_otras_carreras_de_la_materia(): void
    {
        $this->getJson("/api/carreras/{$this->sistemasId}/materias/{$this->calculoId}/grupos")
            ->assertOk()
            ->assertJsonPath('meta.carreras_materia.0.id_carrera', $this->informaticaId)
            ->assertJsonCount(1, 'meta.carreras_materia');
    }

    public function test_editar_suma_una_carrera_y_lo_registra_en_la_bitacora(): void
    {
        $logs = AuditLog::count();

        $this->putJson('/api/grupos/' . $this->grupoPropioId, [
            'num_grupo' => '1',
            'carreras_adicionales' => [$this->informaticaId],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->sistemasId, $this->informaticaId],
            $this->offeringCareers($this->grupoPropioId)
        );
        $this->assertSame($logs + 1, AuditLog::count());
    }

    public function test_editar_sin_enviar_carreras_las_conserva(): void
    {
        $this->addCareer($this->grupoPropioId, $this->informaticaId);

        $this->putJson('/api/grupos/' . $this->grupoPropioId, ['num_grupo' => '9'])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->sistemasId, $this->informaticaId],
            $this->offeringCareers($this->grupoPropioId)
        );
    }

    public function test_no_quita_una_carrera_con_estudiantes_inscritos(): void
    {
        $this->addCareer($this->grupoPropioId, $this->informaticaId);
        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '202400001',
            'nombre' => 'ANA',
            'apellido_paterno' => 'ROJAS',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
            'id_carrera' => $this->informaticaId,
        ]);

        $this->putJson('/api/grupos/' . $this->grupoPropioId, [
            'num_grupo' => '1',
            'carreras_adicionales' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('carreras_adicionales');

        $this->assertContains($this->informaticaId, $this->offeringCareers($this->grupoPropioId));
    }

    public function test_quita_una_carrera_sin_estudiantes(): void
    {
        $this->addCareer($this->grupoPropioId, $this->informaticaId);

        $this->putJson('/api/grupos/' . $this->grupoPropioId, [
            'num_grupo' => '1',
            'carreras_adicionales' => [],
        ])->assertOk();

        $this->assertSame([$this->sistemasId], $this->offeringCareers($this->grupoPropioId));
    }

    /** @return array<int, int> */
    private function offeringCareers(int $groupId): array
    {
        return DB::table('grupo_oferta')
            ->where('id_grupo', $groupId)
            ->orderBy('id_carrera')
            ->pluck('id_carrera')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function addCareer(int $groupId, int $careerId): void
    {
        $group = DB::table('grupo')->where('id_grupo', $groupId)->first();

        DB::table('grupo_oferta')->insertOrIgnore([
            ['id_grupo' => $groupId, 'id_carrera' => (int) $group->id_carrera,
                'id_materia' => (int) $group->id_materia, 'id_gestion_periodo' => (int) $group->id_gestion_periodo],
            ['id_grupo' => $groupId, 'id_carrera' => $careerId,
                'id_materia' => (int) $group->id_materia, 'id_gestion_periodo' => (int) $group->id_gestion_periodo],
        ]);
    }
}
