<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\LateEntryRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Restricciones de solicitud_ingreso_retraso (HU-037): estados válidos, resolución completa,
 * una sola solicitud PENDIENTE por estudiante y examen, y ambiente del propio examen.
 */
class LateEntryRequestSchemaTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const UNIQUE_VIOLATION = '23505';
    private const FOREIGN_KEY_VIOLATION = '23503';
    private const CHECK_VIOLATION = '23514';

    private Exam $exam;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);
        $this->studentId = (int) DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->value('id_estudiante');
    }

    /** @param array<string, string|int|null> $overrides */
    private function insertRequest(array $overrides = []): int
    {
        return (int) DB::table('solicitud_ingreso_retraso')->insertGetId(array_merge([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->studentId,
            'id_ambiente' => $this->aulaId,
            'id_usuario_solicitante' => $this->docenteId,
            'solicitado_en' => '2026-10-31 14:12:00+00',
        ], $overrides), 'id_solicitud');
    }

    /**
     * Ejecuta la inserción dentro de un savepoint: así el error esperado no aborta la
     * transacción de la prueba.
     *
     * @param array<string, string|int|null> $overrides
     */
    private function assertRejected(string $sqlState, array $overrides): void
    {
        try {
            DB::transaction(fn () => $this->insertRequest($overrides));
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
            return;
        }

        $this->fail("Se esperaba el error {$sqlState}.");
    }

    /** @return array<string, string|int|null> */
    private function resolved(string $state): array
    {
        return [
            'estado' => $state,
            'id_usuario_resolutor' => $this->docenteId,
            'resuelto_en' => '2026-10-31 14:20:00+00',
        ];
    }

    public function test_una_solicitud_nueva_queda_pendiente(): void
    {
        $id = $this->insertRequest();

        $request = LateEntryRequest::query()->findOrFail($id);

        $this->assertSame(LateEntryRequest::PENDIENTE, $request->estado);
        $this->assertNull($request->id_usuario_resolutor);
        $this->assertNull($request->resuelto_en);
    }

    public function test_impide_dos_solicitudes_pendientes_del_mismo_estudiante(): void
    {
        $this->insertRequest();

        $this->assertRejected(self::UNIQUE_VIOLATION, []);
    }

    public function test_permite_una_nueva_pendiente_cuando_la_anterior_se_resolvio(): void
    {
        $this->insertRequest($this->resolved(LateEntryRequest::RECHAZADA));
        $this->insertRequest();

        $this->assertSame(2, DB::table('solicitud_ingreso_retraso')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->studentId)
            ->count());
    }

    public function test_rechaza_un_estado_desconocido(): void
    {
        $this->assertRejected(self::CHECK_VIOLATION, $this->resolved('APROBADA'));
    }

    public function test_una_resolucion_exige_resolutor_y_fecha(): void
    {
        $this->assertRejected(self::CHECK_VIOLATION, ['estado' => LateEntryRequest::AUTORIZADA]);
        $this->assertRejected(self::CHECK_VIOLATION, [
            'estado' => LateEntryRequest::AUTORIZADA,
            'id_usuario_resolutor' => $this->docenteId,
        ]);
    }

    public function test_una_pendiente_no_tiene_resolucion(): void
    {
        $this->assertRejected(self::CHECK_VIOLATION, [
            'id_usuario_resolutor' => $this->docenteId,
            'resuelto_en' => '2026-10-31 14:20:00+00',
        ]);
    }

    public function test_el_ambiente_debe_pertenecer_al_examen(): void
    {
        $this->assertRejected(self::FOREIGN_KEY_VIOLATION, ['id_ambiente' => $this->otraAulaId]);
    }

    /** PostgreSQL revierte el DDL junto con la transacción de la prueba. */
    public function test_down_elimina_la_tabla_y_up_la_recrea(): void
    {
        require_once database_path('migrations/2026_10_31_005836_create_solicitud_ingreso_retraso_table.php');
        $migration = new \CreateSolicitudIngresoRetrasoTable();

        $migration->down();
        $this->assertFalse(Schema::hasTable('solicitud_ingreso_retraso'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('solicitud_ingreso_retraso'));
    }
}
