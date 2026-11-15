<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentCareerHistory;
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

    public function test_completa_con_la_carrera_anterior_solo_si_el_grupo_la_atiende(): void
    {
        // 202400001 ya es de Informática; 202400002 es de otra carrera (99); 202400003 es nuevo.
        $history = $this->history(['202400001' => 20, '202400002' => 99]);

        $result = $this->resolve([null, null, null], [self::SISTEMAS, self::INFORMATICA], null, $history);

        $this->assertSame([20, null, null], $this->careerIds($result));
        $this->assertSame(StudentRosterCareerResolver::SOURCE_PREVIOUS, $result->rows()[0]->row()->careerSource());
        $this->assertSame([StudentRosterCareerResolver::MISSING_CAREER], $result->rows()[1]->errors());
        $this->assertSame([StudentRosterCareerResolver::MISSING_CAREER], $result->rows()[2]->errors());
    }

    public function test_la_columna_y_la_eleccion_mandan_y_no_consultan_el_historial(): void
    {
        $history = $this->history(['202400001' => 20, '202400002' => 20]);

        $byColumn = $this->resolve(['SIS', 'SIS'], [self::SISTEMAS, self::INFORMATICA], null, $history);
        $byChoice = $this->resolve([null, null], [self::SISTEMAS, self::INFORMATICA], 10, $history);

        $this->assertSame([10, 10], $this->careerIds($byColumn));
        $this->assertSame([10, 10], $this->careerIds($byChoice));
        $this->assertSame(StudentRosterCareerResolver::SOURCE_CHOSEN, $byChoice->rows()[0]->row()->careerSource());
        $this->assertSame(0, $history->calls);
    }

    public function test_un_grupo_de_una_sola_carrera_no_consulta_el_historial(): void
    {
        $history = $this->history(['202400001' => 20]);

        $result = $this->resolve([null], [self::SISTEMAS], null, $history);

        $this->assertSame([10], $this->careerIds($result));
        $this->assertSame(StudentRosterCareerResolver::SOURCE_ONLY, $result->rows()[0]->row()->careerSource());
        $this->assertSame(0, $history->calls);
    }

    /** @param array<string, int> $careers */
    private function history(array $careers): StudentCareerHistory
    {
        return new class ($careers) extends StudentCareerHistory {
            public int $calls = 0;

            /** @var array<string, int> */
            private array $careers;

            /** @param array<string, int> $careers */
            public function __construct(array $careers)
            {
                $this->careers = $careers;
            }

            public function latestCareerBySis(array $sisCodes): array
            {
                $this->calls++;

                return array_intersect_key($this->careers, array_flip($sisCodes));
            }
        };
    }

    /**
     * @param array<int, string|null> $careerTexts
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string}> $careers
     */
    private function resolve(
        array $careerTexts,
        array $careers,
        ?int $defaultCareerId = null,
        ?StudentCareerHistory $history = null
    ): StudentRosterAnalysisResult {
        $rows = [];

        foreach ($careerTexts as $index => $careerText) {
            $rows[] = new StudentRosterRowAnalysis(
                new StudentRosterRow($index + 2, (string) (202400001 + $index), 'ROJAS', 'ANA', $careerText),
                []
            );
        }

        return (new StudentRosterCareerResolver($history))->resolve(
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
