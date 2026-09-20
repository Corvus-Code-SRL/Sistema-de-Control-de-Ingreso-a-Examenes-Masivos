<?php

namespace Tests\Unit\Services\Academic\Importers;

use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterPreviewStore;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowAnalysis;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

class StudentRosterPreviewStoreTest extends TestCase
{
    public function test_guarda_y_recupera_preview(): void
    {
        $cache = new Repository(new ArrayStore());

        $store = new StudentRosterPreviewStore($cache);

        $analysis = new StudentRosterAnalysisResult([
            new StudentRosterRowAnalysis(
                new StudentRosterRow(
                    2,
                    '20230001',
                    'ROJAS FLORES',
                    'CARLOS'
                ),
                []
            ),
            new StudentRosterRowAnalysis(
                new StudentRosterRow(
                    4,
                    '20230002',
                    null,
                    'MARIA'
                ),
                ['missing_last_names']
            ),
        ]);

        $token = $store->store(
            15,
            '11111111-1111-4111-8111-111111111111',
            $analysis
        );

        $preview = $store->find($token);

        $this->assertNotNull($preview);
        $this->assertSame(15, $preview->groupId());
        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $preview->teacherId()
        );

        $this->assertCount(2, $preview->rows());

        $this->assertSame(
            2,
            $preview->rows()[0]->rowNumber()
        );

        $this->assertSame(
            '20230001',
            $preview->rows()[0]->sisCode()
        );

        $this->assertSame(
            4,
            $preview->rows()[1]->rowNumber()
        );

        $this->assertNull(
            $preview->rows()[1]->lastNames()
        );
    }

    public function test_genera_token_aleatorio_de_256_bits_en_hexadecimal(): void
    {
        $store = new StudentRosterPreviewStore(
            new Repository(new ArrayStore())
        );

        $analysis = new StudentRosterAnalysisResult([]);

        $token = $store->store(
            1,
            'teacher-id',
            $analysis
        );

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );
    }

    public function test_no_usa_el_token_en_texto_plano_como_clave_de_cache(): void
    {
        $cache = new Repository(new ArrayStore());

        $store = new StudentRosterPreviewStore($cache);

        $analysis = new StudentRosterAnalysisResult([]);

        $token = $store->store(
            1,
            'teacher-id',
            $analysis
        );

        $plainKey = 'student-roster-preview:' . $token;

        $hashedKey = 'student-roster-preview:'
            . hash('sha256', $token);

        $this->assertNull(
            $cache->get($plainKey)
        );

        $this->assertIsArray(
            $cache->get($hashedKey)
        );
    }

    public function test_elimina_preview_solo_cuando_se_solicita(): void
    {
        $store = new StudentRosterPreviewStore(
            new Repository(new ArrayStore())
        );

        $analysis = new StudentRosterAnalysisResult([]);

        $token = $store->store(
            1,
            'teacher-id',
            $analysis
        );

        $this->assertNotNull(
            $store->find($token)
        );

        $store->forget($token);

        $this->assertNull(
            $store->find($token)
        );
    }

    public function test_devuelve_null_para_token_inexistente(): void
    {
        $store = new StudentRosterPreviewStore(
            new Repository(new ArrayStore())
        );

        $this->assertNull(
            $store->find(str_repeat('a', 64))
        );
    }

    public function test_rechaza_datos_corruptos_en_cache(): void
    {
        $cache = new Repository(new ArrayStore());

        $store = new StudentRosterPreviewStore($cache);

        $token = str_repeat('b', 64);

        $key = 'student-roster-preview:'
            . hash('sha256', $token);

        $cache->put(
            $key,
            [
                'group_id' => 1,
                'teacher_id' => 'teacher-id',
                'rows' => [
                    [
                        'row_number' => 2,
                        'sis_code' => '20230001',
                    ],
                ],
            ],
            900
        );

        $this->assertNull(
            $store->find($token)
        );
    }
}