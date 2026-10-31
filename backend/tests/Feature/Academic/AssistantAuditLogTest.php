<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-08: cada operación sobre auxiliares queda en la bitácora con su acción del catálogo.
 */
class AssistantAuditLogTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private const OPERATIONS = [
        'ANADIR_AUXILIAR_GRUPO',
        'HABILITAR_AUXILIAR_EXAMEN',
        'QUITAR_AUXILIAR_GRUPO',
        'QUITAR_AUXILIAR_EXAMEN',
    ];

    private string $auxiliarId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
        $this->auxiliarId = $this->createAssistant('Ana', '202400001');
    }

    private function actionId(string $operation): int
    {
        return (int) DB::table('accion')->where('operacion', $operation)->value('id_accion');
    }

    private function assertLoggedAs(string $operation, string $table): void
    {
        $log = AuditLog::where('tabla_afectada', $table)
            ->where('id_accion', $this->actionId($operation))
            ->sole();

        $this->assertNotNull($log->id_accion);
        $this->assertSame($this->docenteId, $log->id_usuario);
    }

    public function test_el_catalogo_incluye_las_cuatro_acciones_y_el_seeder_es_idempotente(): void
    {
        $this->seed(ActionSeeder::class);
        $this->seed(ActionSeeder::class);

        foreach (self::OPERATIONS as $operation) {
            $this->assertSame(1, DB::table('accion')->where('operacion', $operation)->count(), $operation);
        }
    }

    public function test_anadir_habilitar_y_quitar_dejan_una_fila_de_bitacora_con_su_accion(): void
    {
        $this->postJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares", [
            'id_usuario' => $this->auxiliarId,
        ])->assertCreated();
        $this->assertLoggedAs('ANADIR_AUXILIAR_GRUPO', 'grupo_auxiliar');

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->auxiliarId,
        ])->assertCreated();
        $this->assertLoggedAs('HABILITAR_AUXILIAR_EXAMEN', 'examen_auxiliar');

        $this->deleteJson("/api/docente/examenes/{$this->examId}/auxiliares/{$this->auxiliarId}")->assertOk();
        $this->assertLoggedAs('QUITAR_AUXILIAR_EXAMEN', 'examen_auxiliar');

        $this->deleteJson("/api/docente/grupos/{$this->grupoPropioId}/auxiliares/{$this->auxiliarId}")->assertOk();
        $this->assertLoggedAs('QUITAR_AUXILIAR_GRUPO', 'grupo_auxiliar');

        $this->assertSame(0, AuditLog::whereNull('id_accion')->count());
    }

    public function test_anadir_a_varios_grupos_registra_la_accion_de_anadir(): void
    {
        $this->postJson("/api/docente/auxiliares/{$this->auxiliarId}/grupos", [
            'grupos' => [$this->grupoPropioId, $this->segundoGrupoPropioId],
        ])->assertCreated();

        $this->assertLoggedAs('ANADIR_AUXILIAR_GRUPO', 'grupo_auxiliar');
    }
}
