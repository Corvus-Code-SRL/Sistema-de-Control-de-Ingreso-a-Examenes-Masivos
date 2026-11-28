<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU-068: pruebas de exportación de bitácora.
 */
class AuditLogReportExportTest extends TestCase
{
    use AuditLogReportExportTestSupport;

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
