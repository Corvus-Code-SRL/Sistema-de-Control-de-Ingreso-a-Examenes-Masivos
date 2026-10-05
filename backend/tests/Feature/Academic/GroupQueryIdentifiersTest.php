<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * HU-17: los identificadores de las consultas de grupos se validan antes de llegar a la
 * base. Uno no numérico, menor que 1 o por encima del integer de PostgreSQL responde 422;
 * sin ese límite la consulta fallaría con un 500 (SQLSTATE 22003).
 */
class GroupQueryIdentifiersTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /** @dataProvider invalidIdentifiers */
    public function test_el_detalle_rechaza_un_id_de_grupo_invalido(string $id): void
    {
        $this->getJson("/api/grupos/{$id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /** @dataProvider invalidIdentifiers */
    public function test_el_listado_rechaza_una_carrera_invalida(string $id): void
    {
        $this->getJson("/api/carreras/{$id}/materias/{$this->calculoId}/grupos")
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');
    }

    /** @dataProvider invalidIdentifiers */
    public function test_el_listado_rechaza_una_materia_invalida(string $id): void
    {
        $this->getJson("/api/carreras/{$this->sistemasId}/materias/{$id}/grupos")
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');
    }

    /** El valor máximo del integer sigue siendo un id bien formado: no existe, y es 404. */
    public function test_el_id_maximo_valido_responde_404_y_no_500(): void
    {
        $this->getJson('/api/grupos/2147483647')->assertNotFound();
    }

    /** @return array<string, array{0: string}> */
    public function invalidIdentifiers(): array
    {
        return [
            'no numérico' => ['abc'],
            'cero' => ['0'],
            'negativo' => ['-1'],
            'decimal' => ['1.5'],
            'sobre el integer' => ['2147483648'],
            'enorme' => ['99999999999'],
        ];
    }
}
