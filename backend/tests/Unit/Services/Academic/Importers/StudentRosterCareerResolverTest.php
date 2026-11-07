<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterCareerResolver;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowAnalysis;
use PHPUnit\Framework\TestCase;

/**
 * HU-021 (multicarrera): de qué carrera es cada fila de la nómina.
 */
class StudentRosterCareerResolverTest extends TestCase
{
    private const SISTEMAS = ['id_carrera' => 10, 'codigo' => 'SIS', 'nombre' => 'Ingeniería de Sistemas'];

    private const INFORMATICA = ['id_carrera' => 20, 'codigo' => 'INF', 'nombre' => 'Ingeniería Informática'];

    public function test_reconoce_la_carrera_por_codigo_o_nombre_sin_tildes_ni_mayusculas(): void
    {
        $result = $this->resolve(['sis', 'INGENIERIA  INFORMATICA', 'Ingeniería de Sistemas'], [self::SISTEMAS, self::INFORMATICA]);

        $this->assertSame([10, 20, 10], $this->careerIds($result));
        $this->assertSame(3, $result->validRows());
    }

    public function test_una_carrera_ajena_al_grupo_es_un_error_de_la_fila(): void
    {
        $result = $this->resolve(['ELT'], [self::SISTEMAS, self::INFORMATICA]);

        $this->assertSame([null], $this->careerIds($result));
        $this->assertSame([StudentRosterCareerResolver::CAREER_NOT_IN_GROUP], $result->rows()[0]->errors());
    }

    public function test_sin_columna_usa_la_carrera_elegida(): void
    {
        $result = $this->resolve([null, 'SIS'], [self::SISTEMAS, self::INFORMATICA], 20);

        $this->assertSame([20, 10], $this->careerIds($result));
    }

    public function test_sin_columna_ni_eleccion_usa_la_unica_carrera_del_grupo(): void
    {
        $result = $this->resolve([null], [self::SISTEMAS]);

        $this->assertSame([10], $this->careerIds($result));
    }

    public function test_sin_columna_ni_eleccion_en_un_grupo_de_varias_carreras_falta_la_carrera(): void
    {
        $result = $this->resolve([null], [self::SISTEMAS, self::INFORMATICA]);

        $this->assertSame([StudentRosterCareerResolver::MISSING_CAREER], $result->rows()[0]->errors());
    }

    public function test_conserva_los_errores_previos_de_la_fila(): void
    {
        $analysis = new StudentRosterAnalysisResult([
            new StudentRosterRowAnalysis(new StudentRosterRow(2, '202400001', null, 'ANA', 'XX'), ['missing_last_names']),
        ]);

        $result = (new StudentRosterCareerResolver())->resolve($analysis, [self::SISTEMAS], null);

        $this->assertSame(
            ['missing_last_names', StudentRosterCareerResolver::CAREER_NOT_IN_GROUP],
            $result->rows()[0]->errors()
        );
    }

    /**
     * @param array<int, string|null> $careerTexts
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string}> $careers
     */
    private function resolve(array $careerTexts, array $careers, ?int $defaultCareerId = null): StudentRosterAnalysisResult
    {
        $rows = [];

        foreach ($careerTexts as $index => $careerText) {
            $rows[] = new StudentRosterRowAnalysis(
                new StudentRosterRow($index + 2, (string) (202400001 + $index), 'ROJAS', 'ANA', $careerText),
                []
            );
        }

        return (new StudentRosterCareerResolver())->resolve(
            new StudentRosterAnalysisResult($rows),
            $careers,
            $defaultCareerId
        );
    }

    /** @return array<int, int|null> */
    private function careerIds(StudentRosterAnalysisResult $result): array
    {
        return array_map(
            static fn (StudentRosterRowAnalysis $row): ?int => $row->row()->careerId(),
            $result->rows()
        );
    }
}
