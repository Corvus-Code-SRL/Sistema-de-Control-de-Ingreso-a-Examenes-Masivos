<?php

namespace App\Services\Exams;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Convierte en PDF los datos del reporte final que arma FinalReportService (HU-051).
 */
class FinalReportPdfService
{
    /**
     * @return array{nombre_archivo: string, contenido: string}
     */
    public function render(array $report): array
    {
        $header = $report['cabecera'];

        $content = Pdf::loadView('reports.final-report', ['report' => $report])
            ->setPaper('a4')
            ->output();

        return [
            'nombre_archivo' => $this->fileName($header['nombre_examen'], $header['fecha']),
            'contenido' => $content,
        ];
    }

    private function fileName(string $examName, string $date): string
    {
        return 'reporte-final-' . Str::slug($examName) . '-' . $date . '.pdf';
    }
}