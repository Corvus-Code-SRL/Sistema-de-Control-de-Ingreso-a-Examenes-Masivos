<?php

namespace App\Services\Exams;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Convierte en PDF los datos del reporte final que arma FinalReportService (HU-051).
 */
class FinalReportPdfService
{
    private const MEMORY_LIMIT = '512M';

    private const MEMORY_LIMIT_BYTES = 536870912;

    private const TIME_LIMIT_SECONDS = 120;

    /**
     * @return array{nombre_archivo: string, contenido: string}
     */
    public function render(array $report): array
    {
        $header = $report['cabecera'];

        $this->allowHeavyGeneration();

        $content = Pdf::loadView('reports.final-report', ['report' => $report])
            ->setPaper('a4')
            ->output();

        return [
            'nombre_archivo' => $this->fileName($header['nombre_examen'], $header['fecha']),
            'contenido' => $content,
        ];
    }

    /**
     * Un examen masivo agota los 128 MB habituales de PHP dentro de dompdf. El ajuste dura lo que
     * la petición: no se restaura porque PHP no admite bajar el límite por debajo de lo ya usado.
     */
    private function allowHeavyGeneration(): void
    {
        $current = (string) ini_get('memory_limit');

        if ($current !== '-1' && $this->toBytes($current) < self::MEMORY_LIMIT_BYTES) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }

        set_time_limit(self::TIME_LIMIT_SECONDS);
    }

    private function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    private function fileName(string $examName, string $date): string
    {
        return 'reporte-final-' . Str::slug($examName) . '-' . $date . '.pdf';
    }
}