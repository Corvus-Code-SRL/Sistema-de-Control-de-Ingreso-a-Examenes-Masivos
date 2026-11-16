<?php

namespace Tests\Feature\Academic;

use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-023: la lista de estudiantes del grupo marca a los excepcionales y trae su motivo.
 */
class GroupStudentsExceptionalMarkTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private const REASON = 'Convalidación aprobada por la Dirección de Carrera';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
    }

    private function studentRow(int $studentId): ?array
    {
        $rows = $this->getJson("/api/grupos/{$this->grupoPropioId}/estudiantes")
            ->assertOk()
            ->json('data');

        return collect($rows)->firstWhere('id_estudiante', $studentId);
    }

    public function test_la_lista_marca_al_excepcional_con_su_motivo(): void
    {
        $studentId = (int) DB::table('estudiante')->insertGetId([
            'cod_sis' => '202600777',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Mamani',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/estudiantes-excepcionales", [
            'id_estudiante' => $studentId,
            'motivo' => self::REASON,
        ])->assertCreated();

        $row = $this->studentRow($studentId);

        $this->assertNotNull($row);
        $this->assertTrue($row['es_excepcional']);
        $this->assertSame(self::REASON, $row['motivo_excepcional']);
    }

    public function test_un_estudiante_de_la_nomina_no_sale_marcado(): void
    {
        $this->enrollStudentWithSis($this->grupoPropioId, '202600888');
        $studentId = (int) DB::table('estudiante')->where('cod_sis', '202600888')->value('id_estudiante');

        $row = $this->studentRow($studentId);

        $this->assertNotNull($row);
        $this->assertFalse($row['es_excepcional']);
        $this->assertNull($row['motivo_excepcional']);
    }
}
