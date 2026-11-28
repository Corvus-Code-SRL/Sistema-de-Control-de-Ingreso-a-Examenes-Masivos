<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use App\Models\User;
use App\Services\Reports\Security\AuditLogReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-068: pruebas de exportación de bitácora en PDF y Excel.
 */
class AuditLogReportExportTest extends TestCase
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

    public function test_administrator_downloads_valid_excel(): void
    {
        $this->seedOperations();

        $response = $this->get(
            $this->url('excel')
        )->assertOk();

        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->assertStringStartsWith(
            'PK',
            $response->getContent()
        );

        $this->assertStringContainsString(
            'reporte-bitacora-20261101-20261130.xlsx',
            $response->headers->get('Content-Disposition')
        );

        $book = $this->loadSpreadsheet(
            $response->getContent()
        );

        $this->assertSame([
            'Resumen',
            'Totales por acción',
            'Registros',
        ], $book->getSheetNames());

        $book->disconnectWorksheets();
    }

    public function formats(): array
    {
        return [
            'PDF' => ['pdf'],
            'Excel' => ['excel'],
        ];
    }

    /**
     * @dataProvider formats
     */
    public function test_guest_cannot_export(
        string $format
    ): void {
        $this->actAsGuest();

        $this->getJson($this->url($format))
            ->assertUnauthorized();
    }

    /**
     * @dataProvider formats
     */
    public function test_teacher_cannot_export(
        string $format
    ): void {
        $this->actAs(
            $this->createAccount(Role::DOCENTE)
        );

        $this->getJson($this->url($format))
            ->assertForbidden();
    }

    /**
     * @dataProvider formats
     */
    public function test_assistant_cannot_export(
        string $format
    ): void {
        $this->actAs(
            $this->createAccount(Role::AUXILIAR)
        );

        $this->getJson($this->url($format))
            ->assertForbidden();
    }

    /**
     * @dataProvider formats
     */
    public function test_dates_are_required(
        string $format
    ): void {
        $this->getJson(
            '/api/reportes/bitacora/' . $format
        )->assertStatus(422)
            ->assertJsonValidationErrors([
                'desde',
                'hasta',
            ]);
    }

    /**
     * @dataProvider formats
     */
    public function test_inverted_dates_are_rejected(
        string $format
    ): void {
        $this->getJson($this->url($format, [
            'desde' => '2026-11-30',
            'hasta' => '2026-11-01',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('hasta')
            ->assertJsonPath(
                'errors.hasta.0',
                'La fecha de inicio debe ser anterior a la fecha de fin.'
            );
    }

    /**
     * @dataProvider formats
     */
    public function test_empty_report_cannot_be_exported(
        string $format
    ): void {
        $this->getJson($this->url($format))
            ->assertStatus(422)
            ->assertJsonValidationErrors('reporte');
    }

    /**
     * @dataProvider formats
     */
    public function test_invalid_filters_are_rejected(
        string $format
    ): void {
        $this->getJson($this->url($format, [
            'modulo' => 'modulo_inexistente',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('modulo');
    }

    public function test_excel_contains_correct_columns_and_order(): void
    {
        $this->seedOperations();

        $book = $this->workbook();

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame([
            'Fecha y hora',
            'Usuario / Código SIS',
            'Acción',
            'Módulo',
            'Registro afectado',
        ], $sheet->rangeToArray('A1:E1')[0]);

        $this->assertSame(4, $sheet->getHighestRow());

        $this->assertSame(
            '04/11/2026 11:00',
            $sheet->getCell('A2')->getValue()
        );

        $this->assertStringContainsString(
            '20381',
            $sheet->getCell('B2')->getValue()
        );

        $this->assertSame(
            '02/11/2026 11:00',
            $sheet->getCell('A4')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_contains_correct_totals(): void
    {
        $this->seedOperations();

        $book = $this->workbook();

        $summary = $book->getSheetByName('Resumen');

        $this->assertSame(
            3,
            $summary->getCell('B14')->getValue()
        );

        $this->assertSame(
            2,
            $summary->getCell('B15')->getValue()
        );

        $actions = $book->getSheetByName(
            'Totales por acción'
        );

        $this->assertSame(
            2,
            $actions->getCell('B2')->getValue()
        );

        $this->assertSame(
            1,
            $actions->getCell('B3')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_applies_combined_filters(): void
    {
        $this->seedOperations();

        $book = $this->workbook([
            'q' => 'Jose Perez',
            'accion' => 'CREAR',
            'modulo' => 'examenes',
        ]);

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(2, $sheet->getHighestRow());

        $this->assertStringContainsString(
            '20381',
            $sheet->getCell('B2')->getValue()
        );

        $this->assertStringContainsString(
            '13',
            $sheet->getCell('E2')->getValue()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_exports_all_rows_from_page_two(): void
    {
        $this->seedMany(120);

        $book = $this->workbook(['page' => 2]);

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(
            121,
            $sheet->getHighestRow()
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_exports_more_than_one_query_chunk(): void
    {
        $this->seedMany(501);

        $book = $this->workbook();

        $sheet = $book->getSheetByName('Registros');

        $this->assertSame(
            502,
            $sheet->getHighestRow()
        );

        $records = $sheet->rangeToArray('E2:E502');

        $this->assertCount(
            501,
            array_unique(array_column($records, 0))
        );

        $book->disconnectWorksheets();
    }

    public function test_excel_identifies_authenticated_administrator(): void
    {
        $this->seedOperations();

        Carbon::setTestNow(
            Carbon::parse('2026-11-26 15:30:00', 'UTC')
        );

        try {
            $book = $this->workbook([
                'generado_por' => 'Autor falso',
            ]);

            $sheet = $book->getSheetByName('Resumen');

            $this->assertSame(
                $this->admin->nombre_completo
                    . ' (SIS: '
                    . $this->admin->cod_sis
                    . ')',
                $sheet->getCell('B6')->getValue()
            );

            $this->assertSame(
                '26/11/2026 11:30',
                $sheet->getCell('B7')->getValue()
            );

            $book->disconnectWorksheets();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_excel_preserves_text_without_formulas(): void
    {
        $actor = $this->namedAccount(
            '=1+1',
            'Prueba',
            '99881',
            Role::DOCENTE
        );

        $this->logRow(
            $actor,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 90],
            '2026-11-10 15:00:00+00'
        );

        $book = $this->workbook();

        $cell = $book->getSheetByName('Registros')
            ->getCell('B2');

        $this->assertStringStartsWith(
            '=1+1',
            $cell->getValue()
        );

        $this->assertFalse($cell->isFormula());

        $this->assertSame(
            DataType::TYPE_STRING,
            $cell->getDataType()
        );

        $book->disconnectWorksheets();
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

    public function test_exports_do_not_expose_raw_sensitive_values(): void
    {
        $this->logRow(
            $this->firstUser,
            'MODIFICAR',
            'usuario',
            ['contrasenia' => 'SECRET_BEFORE_HU068'],
            [
                'id_usuario' => $this->firstUser->id_usuario,
                'contrasenia' => 'SECRET_AFTER_HU068',
            ],
            '2026-11-10 15:00:00+00'
        );

        $html = $this->pdfHtml();

        $book = $this->workbook();

        $values = json_encode(
            $book->getSheetByName('Registros')->toArray()
        );

        foreach ([
            'SECRET_BEFORE_HU068',
            'SECRET_AFTER_HU068',
            'antiguo_valor',
            'nuevo_valor',
        ] as $secret) {
            $this->assertStringNotContainsString(
                $secret,
                $html
            );

            $this->assertStringNotContainsString(
                $secret,
                $values
            );
        }

        $book->disconnectWorksheets();
    }

    /**
     * @dataProvider formats
     */
    public function test_export_does_not_modify_audit_log(
        string $format
    ): void {
        $this->seedOperations();

        $before = DB::table('log')
            ->orderBy('id_log')
            ->get()
            ->toArray();

        $this->get($this->url($format))
            ->assertOk();

        $after = DB::table('log')
            ->orderBy('id_log')
            ->get()
            ->toArray();

        $this->assertEquals($before, $after);
    }

    /**
     * @dataProvider formats
     */
    public function test_export_disables_response_caching(
        string $format
    ): void {
        $this->seedOperations();

        $response = $this->get(
            $this->url($format)
        )->assertOk();

        $cache = $response->headers->get(
            'Cache-Control'
        );

        $this->assertStringContainsString(
            'private',
            $cache
        );

        $this->assertStringContainsString(
            'no-store',
            $cache
        );
    }
}
