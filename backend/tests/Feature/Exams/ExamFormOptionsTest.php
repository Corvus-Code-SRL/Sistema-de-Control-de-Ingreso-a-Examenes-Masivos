<?php

namespace Tests\Feature\Exams;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Catálogos del formulario de creación de examen.
 */
class ExamFormOptionsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    public function test_ofrece_pares_y_ambientes_activos_y_los_grupos_del_docente_configurado(): void
    {
        $this->seedExamCatalog();

        $data = $this->getJson('/api/examenes/formulario')->assertOk()->json('data');

        // 5 pares en el catálogo: se excluyen el de materia inactiva y el par inactivo.
        $pairs = collect($data['materias'])
            ->map(fn (array $pair) => [$pair['id_carrera'], $pair['id_materia']])
            ->all();

        $this->assertEqualsCanonicalizing([
            [$this->sistemasId, $this->calculoId],
            [$this->informaticaId, $this->calculoId],
            [$this->sistemasId, $this->basesDatosId],
        ], $pairs);

        $this->assertEqualsCanonicalizing(
            [$this->aulaId, $this->otraAulaId],
            array_column($data['ambientes'], 'id_ambiente')
        );

        // Solo los grupos propios del periodo activo; nunca los de otro docente.
        $this->assertCount(2, $data['grupos']);
        $this->assertNotContains($this->grupoAjenoId, array_column($data['grupos'], 'id_grupo'));
        $this->assertNotContains($this->grupoPeriodoAnteriorId, array_column($data['grupos'], 'id_grupo'));

        $ownGroup = collect($data['grupos'])->firstWhere('id_grupo', $this->grupoPropioId);
        $this->assertSame(2, $ownGroup['cantidad_estudiantes']);
        $this->assertTrue($ownGroup['tiene_nomina']);
    }
}
