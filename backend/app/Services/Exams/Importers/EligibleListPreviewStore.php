<?php

namespace App\Services\Exams\Importers;

use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowAnalysis;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Guarda el preview de la lista de habilitados en caché, con el mismo
 * patrón que StudentRosterPreviewStore (HU-021): token opaco de 64 hex,
 * clave derivada con sha256, TTL de 15 minutos.
 */
class EligibleListPreviewStore
{
    private const CACHE_PREFIX = 'eligible-list-preview:';

    private const TTL_SECONDS = 900;

    private CacheRepository $cache;

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    public function store(
        int $examId,
        string $teacherId,
        StudentRosterAnalysisResult $analysis
    ): string {
        $token = bin2hex(random_bytes(32));

        $rows = array_map(
            static function (StudentRosterRowAnalysis $analysisRow): array {
                $row = $analysisRow->row();

                return [
                    'row_number' => $row->rowNumber(),
                    'sis_code' => $row->sisCode(),
                    'last_names' => $row->lastNames(),
                    'first_names' => $row->firstNames(),
                ];
            },
            $analysis->rows()
        );

        $this->cache->put(
            $this->cacheKey($token),
            [
                'exam_id' => $examId,
                'teacher_id' => $teacherId,
                'rows' => $rows,
            ],
            self::TTL_SECONDS
        );

        return $token;
    }

    public function find(string $token): ?EligibleListPreview
    {
        $data = $this->cache->get($this->cacheKey($token));

        if (!is_array($data)) {
            return null;
        }

        if (
            !isset($data['exam_id'], $data['teacher_id'], $data['rows'])
            || !is_array($data['rows'])
        ) {
            return null;
        }

        $rows = [];

        foreach ($data['rows'] as $row) {
            if (
                !is_array($row)
                || !array_key_exists('row_number', $row)
                || !array_key_exists('sis_code', $row)
                || !array_key_exists('last_names', $row)
                || !array_key_exists('first_names', $row)
            ) {
                return null;
            }

            $rows[] = new StudentRosterRow(
                (int) $row['row_number'],
                $this->nullableString($row['sis_code']),
                $this->nullableString($row['last_names']),
                $this->nullableString($row['first_names'])
            );
        }

        return new EligibleListPreview(
            (int) $data['exam_id'],
            (string) $data['teacher_id'],
            $rows
        );
    }

    public function forget(string $token): void
    {
        $this->cache->forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }

    /**
     * @param mixed $value
     */
    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
