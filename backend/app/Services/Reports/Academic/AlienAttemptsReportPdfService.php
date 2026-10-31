<?php

namespace App\Services\Reports\Academic;

use Barryvdh\DomPDF\Facade\Pdf;

class AlienAttemptsReportPdfService
{
    public function render(array $report): array
    {
        $content = Pdf::loadView('reports.alien-attempts', ['report' => $report])
            ->setPaper('a4')
            ->output();

        $name = 'reporte-intentos-ajenos-'
            . str_replace('-', '', $report['cabecera']['desde'])
            . '-'
            . str_replace('-', '', $report['cabecera']['hasta'])
            . '.pdf';

        return ['nombre_archivo' => $name, 'contenido' => $content];
    }
}
