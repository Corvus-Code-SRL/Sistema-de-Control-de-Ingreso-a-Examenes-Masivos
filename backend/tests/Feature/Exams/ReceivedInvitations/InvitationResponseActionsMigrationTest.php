<?php

namespace Tests\Feature\Exams\ReceivedInvitations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/** HU-027: la migración de las acciones de bitácora se puede repetir y no se deja revertir con historial. */
class InvitationResponseActionsMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    private const ACTIONS = ['ACEPTAR_INVITACION', 'RECHAZAR_INVITACION'];

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_11_17_000000_add_invitation_response_actions.php');
    }

    private function migration(): \AddInvitationResponseActions
    {
        return new \AddInvitationResponseActions();
    }

    /** @return array<string, string> */
    private function actions(): array
    {
        return DB::table('accion')
            ->whereIn('operacion', self::ACTIONS)
            ->orderBy('operacion')
            ->pluck('tipo_operacion', 'operacion')
            ->all();
    }

    public function test_registra_las_dos_acciones_una_sola_vez_aunque_se_repita(): void
    {
        $expected = ['ACEPTAR_INVITACION' => 'UPDATE', 'RECHAZAR_INVITACION' => 'UPDATE'];

        $this->assertSame($expected, $this->actions());

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame($expected, $this->actions());
        $this->assertSame(2, DB::table('accion')->whereIn('operacion', self::ACTIONS)->count());
    }

    public function test_down_quita_las_acciones_si_no_se_usaron(): void
    {
        $this->migration()->down();

        $this->assertSame([], $this->actions());

        $this->migration()->up();
        $this->assertCount(2, $this->actions());
    }

    public function test_down_aborta_sin_tocar_nada_si_hay_filas_de_bitacora(): void
    {
        $this->seedAcademicCatalog();
        DB::table('log')->insert([
            'id_accion' => DB::table('accion')->where('operacion', 'RECHAZAR_INVITACION')->value('id_accion'),
            'tabla_afectada' => 'invitacion_examen',
            'id_usuario' => $this->docenteId,
        ]);

        try {
            $this->migration()->down();
            $this->fail('down() debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('hay 1 fila(s) de bitácora', $exception->getMessage());
        }

        $this->assertCount(2, $this->actions());
    }
}
