<?php

namespace App\Services\Reports\Academic;

use Barryvdh\DomPDF\Facade\Pdf;

class AlienAttemptsReportPdfService
{
    private const MEMORY_LIMIT_BYTES = 512 * 1024 * 1024;

    public function render(array $report): array
    {
        $previousLimit = $this->raiseMemoryLimitIfNeeded();

        try {
            $content = Pdf::loadView('reports.alien-attempts', ['report' => $report])
                ->setPaper('a4')
                ->output();
        } finally {
            $this->restoreMemoryLimit($previousLimit);
        }

        $name = 'reporte-intentos-ajenos-'
            . str_replace('-', '', $report['cabecera']['desde'])
            . '-'
            . str_replace('-', '', $report['cabecera']['hasta'])
            . '.pdf';

        return ['nombre_archivo' => $name, 'contenido' => $content];
    }

    private function raiseMemoryLimitIfNeeded(): string
    {
        $current = (string) ini_get('memory_limit');
        $bytes = $this->toBytes($current);

        if ($bytes === -1 || $bytes >= self::MEMORY_LIMIT_BYTES) {
            return $current;
        }

        ini_set('memory_limit', '512M');

        return $current;
    }

    private function restoreMemoryLimit(string $previous): void
    {
        if ($previous !== '') {
            ini_set('memory_limit', $previous);
        }
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}