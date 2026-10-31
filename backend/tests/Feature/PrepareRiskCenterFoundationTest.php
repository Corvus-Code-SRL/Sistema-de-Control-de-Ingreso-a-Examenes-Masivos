<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * Migración que prepara reporte_estudiante para la central de riesgo (id_intento y evidencia opcional).
 *
 * El DDL de PostgreSQL es transaccional: cada prueba revierte, junto con sus filas, lo que up() y down()
 * cambien en el esquema.
 */
class PrepareRiskCenterFoundationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    private const CONSTRAINTS = [
        'unq_reporte_estudiante_id_intento',
        'fk_reporte_estudiante_intento',
        'chk_reporte_estudiante_evidencia',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_10_15_000000_prepare_risk_center_foundation.php');
    }

    private function migration(): \PrepareRiskCenterFoundation
    {
        return new \PrepareRiskCenterFoundation();
    }

    /** @return array<string, string> columna => is_nullable */
    private function evidenceNullability(): array
    {
        return collect(DB::select(
            "select column_name, is_nullable from information_schema.columns
             where table_schema = 'public' and table_name = 'reporte_estudiante'
               and column_name in ('evidencia_url', 'evidencia_public_id') order by column_name"
        ))->pluck('is_nullable', 'column_name')->all();
    }

    private function hasIdIntento(): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'reporte_estudiante')
            ->where('column_name', 'id_intento')
            ->exists();
    }

    /** @return array<int, string> las restricciones de la migración que existen, ordenadas */
    private function constraints(): array
    {
        return DB::table('pg_constraint')
            ->whereRaw("conrelid = 'public.reporte_estudiante'::regclass")
            ->whereIn('conname', self::CONSTRAINTS)
            ->orderBy('conname')
            ->pluck('conname')
            ->all();
    }

    private function assertFoundationApplied(): void
    {
        $this->assertTrue($this->hasIdIntento());
        $this->assertSame(['evidencia_public_id' => 'YES', 'evidencia_url' => 'YES'], $this->evidenceNullability());
        $this->assertSame(
            ['chk_reporte_estudiante_evidencia', 'fk_reporte_estudiante_intento', 'unq_reporte_estudiante_id_intento'],
            $this->constraints()
        );
    }

    private function assertFoundationReverted(): void
    {
        $this->assertFalse($this->hasIdIntento());
        $this->assertSame(['evidencia_public_id' => 'NO', 'evidencia_url' => 'NO'], $this->evidenceNullability());
        $this->assertSame([], $this->constraints());
    }

    public function test_el_esquema_de_pruebas_ya_tiene_la_base_aplicada(): void
    {
        $this->assertFoundationApplied();
    }

    public function test_down_revierte_y_up_vuelve_a_aplicar(): void
    {
        $this->migration()->down();
        $this->assertFoundationReverted();

        $this->migration()->up();
        $this->assertFoundationApplied();
    }

    public function test_up_es_idempotente(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertFoundationApplied();
    }

    public function test_down_es_idempotente_cuando_ya_esta_revertida(): void
    {
        $this->migration()->down();
        $this->migration()->down();

        $this->assertFoundationReverted();
    }

    public function test_up_conserva_los_reportes_existentes(): void
    {
        $this->migration()->down();
        $context = $this->createReportingContext();
        $reportId = $this->insertStudentReport($context, [
            'evidencia_url' => 'evidencias/reportes/1.png',
            'evidencia_public_id' => 'evidencias/reportes/1.png',
        ]);

        $this->migration()->up();

        $report = DB::table('reporte_estudiante')->where('id_reporte_est', $reportId)->first();
        $this->assertSame('evidencias/reportes/1.png', $report->evidencia_url);
        $this->assertNull($report->id_intento);
    }

    public function test_down_aborta_sin_cambiar_nada_si_hay_reportes_sin_evidencia(): void
    {
        $this->insertStudentReport($this->createReportingContext());

        try {
            $this->migration()->down();
            $this->fail('down() debía abortar porque hay un reporte sin evidencia.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se modificó nada', $exception->getMessage());
            $this->assertStringContainsString('1 fila(s) sin evidencia', $exception->getMessage());
        }

        $this->assertFoundationApplied();
    }

    public function test_down_aborta_sin_cambiar_nada_si_un_reporte_tiene_intento(): void
    {
        $context = $this->createReportingContext();
        $attemptId = $this->insertEntryAttempt($context);
        $this->insertStudentReport($context, [
            'id_intento' => $attemptId,
            'evidencia_url' => 'evidencias/reportes/1.png',
            'evidencia_public_id' => 'evidencias/reportes/1.png',
        ]);

        try {
            $this->migration()->down();
            $this->fail('down() debía abortar porque un reporte tiene id_intento.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('1 fila(s) con id_intento', $exception->getMessage());
        }

        $this->assertFoundationApplied();
        $this->assertSame(1, DB::table('reporte_estudiante')->where('id_intento', $attemptId)->count());
    }

    public function test_down_revierte_si_todos_los_reportes_tienen_evidencia_y_ninguno_intento(): void
    {
        $this->insertStudentReport($this->createReportingContext(), [
            'evidencia_url' => 'evidencias/reportes/1.png',
            'evidencia_public_id' => 'evidencias/reportes/1.png',
        ]);

        $this->migration()->down();

        $this->assertFoundationReverted();
        $this->assertSame(1, DB::table('reporte_estudiante')->count());
    }
}
