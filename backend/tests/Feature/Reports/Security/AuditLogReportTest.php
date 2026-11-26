<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-068: audit log report endpoint tests.
 */
class AuditLogReportTest extends TestCase
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

    private function reportUrl(array $extra = []): string
    {
        $filters = array_merge([
            'desde' => '2026-11-01',
            'hasta' => '2026-11-30',
        ], $extra);

        return '/api/reportes/bitacora?' . http_build_query($filters);
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

    public function test_administrator_can_generate_report(): void
    {
        $this->seedOperations();

        $this->getJson($this->reportUrl())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('totales.total_operaciones', 3)
            ->assertJsonPath('totales.total_usuarios', 2)
            ->assertJsonPath('puede_exportar', true)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('data.0.usuario.cod_sis', '20381')
            ->assertJsonPath('filtros.desde', '2026-11-01')
            ->assertJsonPath('filtros.hasta', '2026-11-30')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_log',
                        'fecha_hora',
                        'usuario',
                        'accion',
                        'modulo',
                        'registro',
                        'automatico',
                    ],
                ],
                'totales' => [
                    'total_operaciones',
                    'total_usuarios',
                    'por_accion' => [
                        '*' => ['codigo', 'etiqueta', 'total'],
                    ],
                ],
                'filtros',
                'meta',
                'puede_exportar',
            ]);
    }

    public function test_guest_receives_401(): void
    {
        $this->actAsGuest();

        $this->getJson($this->reportUrl())
            ->assertUnauthorized();
    }

    public function test_non_administrator_roles_receive_403(): void
    {
        $guest = User::factory()->create([
            'cod_sis' => 'INV4821',
        ]);

        $this->giveRole($guest, Role::INVITADO);

        $accounts = [
            $this->firstUser,
            $this->createAccount(Role::AUXILIAR),
            $guest,
        ];

        foreach ($accounts as $account) {
            $this->actAs($account);

            $this->getJson($this->reportUrl())
                ->assertForbidden();
        }
    }

    public function test_account_without_role_receives_403(): void
    {
        $this->actAs($this->createAccount());

        $this->getJson($this->reportUrl())
            ->assertForbidden();
    }

    public function test_inactive_administrator_cannot_access(): void
    {
        $inactive = $this->createAccount(
            Role::ADMINISTRADOR,
            User::ESTADO_INACTIVO
        );

        $this->actAs($inactive);

        $response = $this->getJson($this->reportUrl());

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403]
        );
    }

    public function test_both_dates_are_required(): void
    {
        $this->getJson('/api/reportes/bitacora')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde', 'hasta']);

        $this->getJson('/api/reportes/bitacora?desde=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->getJson('/api/reportes/bitacora?hasta=2026-11-30')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde']);
    }

    public function test_reversed_dates_are_rejected(): void
    {
        $this->getJson($this->reportUrl([
            'desde' => '2026-11-30',
            'hasta' => '2026-11-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta'])
            ->assertJsonPath(
                'errors.hasta.0',
                'La fecha de inicio debe ser anterior a la fecha de fin.'
            );
    }

    public function test_same_day_range_is_valid(): void
    {
        $this->seedOperations();

        $this->getJson($this->reportUrl([
            'desde' => '2026-11-04',
            'hasta' => '2026-11-04',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('totales.total_operaciones', 1);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->getJson($this->reportUrl([
            'desde' => '01/11/2026',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde']);
    }

    public function test_combined_filters_are_applied(): void
    {
        $this->seedOperations();

        $this->getJson($this->reportUrl([
            'q' => 'jose perez',
            'accion' => 'CREAR',
            'modulo' => 'examenes',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('totales.total_operaciones', 1)
            ->assertJsonPath('totales.total_usuarios', 1)
            ->assertJsonPath('data.0.usuario.cod_sis', '20381')
            ->assertJsonPath('data.0.accion.codigo', 'CREAR')
            ->assertJsonPath('data.0.modulo.clave', 'examenes')
            ->assertJsonPath('totales.por_accion.0.codigo', 'CREAR')
            ->assertJsonPath('totales.por_accion.0.total', 1);
    }

    public function test_empty_report_disables_export(): void
    {
        $this->seedOperations();

        $this->getJson($this->reportUrl([
            'q' => 'usuario inexistente',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('totales.total_operaciones', 0)
            ->assertJsonPath('totales.total_usuarios', 0)
            ->assertJsonPath('totales.por_accion', [])
            ->assertJsonPath('puede_exportar', false);
    }

    public function test_pagination_does_not_limit_totals(): void
    {
        foreach (range(1, 120) as $i) {
            $this->logRow(
                $this->firstUser,
                'CREAR',
                'ambiente',
                null,
                ['id_ambiente' => $i],
                '2026-11-05 15:00:00+00'
            );
        }

        $this->getJson($this->reportUrl(['page' => 2]))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 120)
            ->assertJsonPath('totales.total_operaciones', 120)
            ->assertJsonPath('totales.total_usuarios', 1)
            ->assertJsonPath('totales.por_accion.0.total', 120)
            ->assertJsonPath('puede_exportar', true);
    }

    public function test_report_does_not_expose_raw_sensitive_data(): void
    {
        $this->logRow(
            $this->firstUser,
            'MODIFICAR',
            'usuario',
            ['contrasenia' => 'secret_before_test'],
            [
                'id_usuario' => $this->firstUser->id_usuario,
                'contrasenia' => 'secret_after_test',
            ],
            '2026-11-05 15:00:00+00'
        );

        $response = $this->getJson($this->reportUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $row = $response->json('data.0');

        $this->assertArrayNotHasKey('antiguo_valor', $row);
        $this->assertArrayNotHasKey('nuevo_valor', $row);

        $this->assertStringNotContainsString(
            'secret_before_test',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'secret_after_test',
            $response->getContent()
        );
    }

    public function test_generating_report_does_not_modify_audit_log(): void
    {
        $this->seedOperations();

        $before = DB::table('log')
            ->orderBy('id_log')
            ->get();

        $this->getJson($this->reportUrl())
            ->assertOk();

        $after = DB::table('log')
            ->orderBy('id_log')
            ->get();

        $this->assertEquals($before, $after);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $invalidFilters = [
            'accion' => 'crear',
            'modulo' => 'modulo_inexistente',
            'page' => 0,
            'q' => str_repeat('a', 101),
        ];

        foreach ($invalidFilters as $field => $value) {
            $this->getJson($this->reportUrl([
                $field => $value,
            ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }
    }
}