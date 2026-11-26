<?php

namespace Tests\Feature\Reports\Security;

use App\Models\Role;
use App\Models\User;
use App\Services\Security\AuditLogQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class AuditLogReportQueryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private User $firstUser;
    private User $secondUser;
    private AuditLogQueryService $service;

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

        $this->service = app(AuditLogQueryService::class);
    }

    private function filters(array $extra = []): array
    {
        return array_merge([
            'desde' => '2026-11-01',
            'hasta' => '2026-11-30',
        ], $extra);
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

    public function test_totals_include_all_filtered_operations(): void
    {
        $this->seedOperations();

        $totals = $this->service->reportTotals($this->filters());

        $this->assertSame(3, $totals['total_operaciones']);
        $this->assertSame(2, $totals['total_usuarios']);

        $actions = array_column(
            $totals['por_accion'],
            'total',
            'codigo'
        );

        $this->assertSame(2, $actions['CREAR']);
        $this->assertSame(1, $actions['MODIFICAR']);
    }

    public function test_totals_respect_combined_filters(): void
    {
        $this->seedOperations();

        $totals = $this->service->reportTotals(
            $this->filters([
                'q' => 'jose perez',
                'accion' => 'CREAR',
                'modulo' => 'examenes',
            ])
        );

        $this->assertSame(1, $totals['total_operaciones']);
        $this->assertSame(1, $totals['total_usuarios']);
        $this->assertCount(1, $totals['por_accion']);
        $this->assertSame(
            'CREAR',
            $totals['por_accion'][0]['codigo']
        );
    }

    public function test_empty_report_returns_zero_totals(): void
    {
        $this->seedOperations();

        $filters = $this->filters([
            'q' => 'usuario inexistente',
        ]);

        $this->assertSame([
            'total_operaciones' => 0,
            'total_usuarios' => 0,
            'por_accion' => [],
        ], $this->service->reportTotals($filters));

        $this->assertSame(
            [],
            iterator_to_array(
                $this->service->reportRows($filters),
                false
            )
        );
    }

    public function test_export_rows_are_ordered_and_presented(): void
    {
        $this->seedOperations();

        $rows = iterator_to_array(
            $this->service->reportRows($this->filters()),
            false
        );

        $this->assertCount(3, $rows);

        $this->assertSame(
            '20381',
            $rows[0]['usuario']['cod_sis']
        );

        $this->assertSame(
            'CREAR',
            $rows[0]['accion']['codigo']
        );

        $this->assertSame(
            'examenes',
            $rows[0]['modulo']['clave']
        );

        $this->assertSame(
            'Examen #13',
            $rows[0]['registro']['descripcion']
        );

        $this->assertArrayNotHasKey('antiguo_valor', $rows[0]);
        $this->assertArrayNotHasKey('nuevo_valor', $rows[0]);
    }

    public function test_export_is_not_limited_by_screen_pagination(): void
    {
        $ids = [];

        foreach (range(1, 120) as $i) {
            $ids[] = $this->logRow(
                $this->firstUser,
                'CREAR',
                'ambiente',
                null,
                ['id_ambiente' => $i],
                '2026-11-05 15:00:00+00'
            );
        }

        $filters = $this->filters(['page' => 2]);

        $page = $this->service->paginate($filters);

        $rows = iterator_to_array(
            $this->service->reportRows($filters),
            false
        );

        $totals = $this->service->reportTotals($filters);

        $this->assertSame(120, $page->total());
        $this->assertSame(50, $page->count());
        $this->assertCount(120, $rows);
        $this->assertSame(120, $totals['total_operaciones']);
        $this->assertSame(1, $totals['total_usuarios']);

        $this->assertSame(
            array_reverse($ids),
            array_column($rows, 'id_log')
        );
    }

    public function test_export_includes_more_than_one_processing_chunk(): void
    {
        $ids = [];

        foreach (range(1, 501) as $i) {
            $ids[] = $this->logRow(
                $this->firstUser,
                'CREAR',
                'ambiente',
                null,
                ['id_ambiente' => $i],
                '2026-11-05 15:00:00+00'
            );
        }

        $rows = iterator_to_array(
            $this->service->reportRows($this->filters()),
            false
        );

        $this->assertCount(501, $rows);

        $this->assertSame(
            array_reverse($ids),
            array_column($rows, 'id_log')
        );
    }
}