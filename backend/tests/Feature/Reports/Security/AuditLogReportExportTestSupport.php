<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use App\Models\User;
use App\Services\Reports\Security\AuditLogReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;

/**
 * Preparación y funciones compartidas de las pruebas de exportación de bitácora.
 */
trait AuditLogReportExportTestSupport
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private User $firstUser;
    private User $secondUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareAuditLog();

        $this->firstUser = $this->namedAccount(
            'Marcelo',
            'Quiroga',
            '10452',
            Role::DOCENTE
        );

        $this->secondUser = $this->namedAccount(
            'José',
            'Pérez',
            '20381',
            Role::DOCENTE
        );
    }

    private function filters(array $extra = []): array
    {
        return array_merge([
            'desde' => '2026-11-01',
            'hasta' => '2026-11-30',
        ], $extra);
    }

    private function url(
        string $format,
        array $extra = []
    ): string {
        return '/api/reportes/bitacora/'
            . $format
            . '?'
            . http_build_query($this->filters($extra));
    }

    private function seedOperations(): void
    {
        $this->logRow(
            $this->firstUser,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 12],
            '2026-11-02 15:00:00+00'
        );

        $this->logRow(
            $this->firstUser,
            'MODIFICAR',
            'grupo',
            ['id_grupo' => 7],
            ['id_grupo' => 7],
            '2026-11-03 15:00:00+00'
        );

        $this->logRow(
            $this->secondUser,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 13],
            '2026-11-04 15:00:00+00'
        );

        $this->logRow(
            $this->secondUser,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 14],
            '2026-10-15 15:00:00+00'
        );
    }

    private function seedMany(int $quantity): void
    {
        for ($i = 1; $i <= $quantity; $i++) {
            $this->logRow(
                $this->firstUser,
                'CREAR',
                'examen',
                null,
                ['id_examen' => $i],
                '2026-11-10 15:00:00+00'
            );
        }
    }

    private function loadSpreadsheet(
        string $content
    ): Spreadsheet {
        $path = tempnam(sys_get_temp_dir(), 'hu068');

        try {
            file_put_contents($path, $content);

            return IOFactory::createReader('Xlsx')
                ->load($path);
        } finally {
            @unlink($path);
        }
    }

    private function workbook(
        array $extra = []
    ): Spreadsheet {
        $response = $this->get(
            $this->url('excel', $extra)
        )->assertOk();

        return $this->loadSpreadsheet(
            $response->getContent()
        );
    }

    private function pdfHtml(
        array $extra = []
    ): string {
        $report = app(AuditLogReportService::class)
            ->export(
                $this->filters($extra),
                $this->admin->id_usuario
            );

        return view(
            'reports.audit-log-report',
            ['report' => $report]
        )->render();
    }
}
