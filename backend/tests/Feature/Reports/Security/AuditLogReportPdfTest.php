<?php

namespace Tests\Feature\Reports\Security;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * HU-068: pruebas de exportación de bitácora.
 */
class AuditLogReportPdfTest extends TestCase
{
    use AuditLogReportExportTestSupport;

    public function test_administrator_downloads_valid_pdf(): void
    {
        $this->seedOperations();

        $response = $this->get(
            $this->url('pdf')
        )->assertOk();

        $response->assertHeader(
            'Content-Type',
            'application/pdf'
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );

        $this->assertStringContainsString(
            '%%EOF',
            substr($response->getContent(), -1024)
        );

        $this->assertStringContainsString(
            'reporte-bitacora-20261101-20261130.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_pdf_template_contains_report_header(): void
    {
        $this->seedOperations();

        Carbon::setTestNow(
            Carbon::parse('2026-11-26 15:30:00', 'UTC')
        );

        try {
            $html = $this->pdfHtml();

            $this->assertStringContainsString(
                'Reporte de bitácora',
                $html
            );

            $this->assertStringContainsString(
                e($this->admin->nombre_completo),
                $html
            );

            $this->assertStringContainsString(
                '26/11/2026 11:30',
                $html
            );

            $this->assertStringContainsString(
                '01/11/2026',
                $html
            );

            $this->assertStringContainsString(
                '30/11/2026',
                $html
            );

            $this->assertStringContainsString(
                'Total de operaciones',
                $html
            );

            $this->assertStringContainsString(
                'Usuarios distintos',
                $html
            );

            $this->assertStringContainsString(
                'Totales por acción',
                $html
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pdf_template_applies_combined_filters(): void
    {
        $this->seedOperations();

        $html = $this->pdfHtml([
            'q' => 'Jose Perez',
            'accion' => 'CREAR',
            'modulo' => 'examenes',
        ]);

        $this->assertStringContainsString(
            '20381',
            $html
        );

        $this->assertStringNotContainsString(
            'Marcelo Quiroga',
            $html
        );

        $this->assertStringContainsString(
            '04/11/2026 11:00',
            $html
        );

        $this->assertStringNotContainsString(
            '15/10/2026',
            $html
        );
    }

    public function test_pdf_template_includes_all_rows(): void
    {
        $this->seedMany(501);

        $html = $this->pdfHtml(['page' => 2]);

        $this->assertSame(
            501,
            substr_count($html, 'SIS: 10452')
        );
    }

    public function test_large_pdf_can_be_generated(): void
    {
        $this->seedMany(501);

        $response = $this->get(
            $this->url('pdf', ['page' => 2])
        )->assertOk();

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }
}
