<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-08: el listado "Mis auxiliares" no hace una consulta por auxiliar ni por grupo.
 */
class AssistantListQueryCountTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
        DB::table('grupo_examen')->insert(['id_grupo' => $this->segundoGrupoPropioId, 'id_examen' => $this->examId]);
        $this->enrollStudents($this->grupoPropioId, 3);
    }

    private function addAssistants(int $amount, int $offset): void
    {
        for ($i = $offset; $i < $offset + $amount; $i++) {
            $id = $this->createAssistant('Auxiliar' . $i, (string) (202500000 + $i));
            $this->putInGroup($id, $this->grupoPropioId);
            $this->putInGroup($id, $this->segundoGrupoPropioId);

            if ($i % 2 === 0) {
                $this->enableInExam($id, $this->examId);
            }
        }
    }

    private function listQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/docente/auxiliares')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_la_cantidad_de_consultas_no_crece_con_mas_auxiliares(): void
    {
        $this->addAssistants(2, 1);
        $few = $this->listQueryCount();

        $this->addAssistants(8, 3);
        $many = $this->listQueryCount();

        $this->assertSame($few, $many, 'El listado hace una consulta por auxiliar o por grupo.');
        $this->assertLessThanOrEqual(12, $many);
    }

    public function test_el_listado_reparte_grupos_examenes_habilitados_y_disponibles(): void
    {
        $this->addAssistants(2, 1);
        $segundoExamen = $this->createExamLinkedTo([$this->segundoGrupoPropioId], 'PROGRAMADO', null, 'Segundo');

        $rows = collect($this->getJson('/api/docente/auxiliares')->assertOk()->json('data'));

        $this->assertCount(2, $rows);

        // El auxiliar 1 (impar) no está habilitado en nada: ambos exámenes están disponibles.
        $odd = $rows->first(fn ($row) => $row['cod_sis'] === '202500001');
        $this->assertCount(2, $odd['grupos']);
        $this->assertSame([], $odd['examenes']);
        $this->assertEqualsCanonicalizing([$this->examId, $segundoExamen], array_column($odd['examenes_disponibles'], 'id_examen'));

        // El auxiliar 2 (par) ya está habilitado en el primero: solo queda disponible el segundo.
        $even = $rows->first(fn ($row) => $row['cod_sis'] === '202500002');
        $this->assertSame([$this->examId], array_column($even['examenes'], 'id_examen'));
        $this->assertSame([$segundoExamen], array_column($even['examenes_disponibles'], 'id_examen'));
        $this->assertTrue($even['grupos'][0]['tiene_examen_programado']);
    }
}
