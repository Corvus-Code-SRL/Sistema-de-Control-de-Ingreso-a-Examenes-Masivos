<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Services\Exams\Distribution\DistributionRosterQuery;
use App\Services\Exams\Distribution\DistributionVersion;
use App\Support\Exams\DistributionMessages;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionIntegrityTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_una_version_anterior_no_sobrescribe_un_guardado_mas_reciente(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertStatus(409)->assertJsonPath('message', DistributionMessages::STALE);
        $this->assertSame($saved, $this->getJson($this->distributionUrl($exam))->json('data'));
        $this->assertSame(1, DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->count());
    }

    public function test_cada_guardado_audita_solo_los_cambios_con_anterior_nuevo_y_autor(): void
    {
        $exam = $this->distributionExam();
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $assignments = $this->distributionProposal($exam);
        $assignments[0]['id_ambiente'] = $this->otraAulaId;
        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertOk();
        $logs = DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->orderBy('id_log')->get();
        $before = json_decode($logs[1]->antiguo_valor, true, 512, JSON_THROW_ON_ERROR);
        $after = json_decode($logs[1]->nuevo_valor, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(2, $logs);
        $this->assertSame($this->docenteId, $logs[1]->id_usuario);
        $this->assertSame(1, $after['total_cambios']);
        $this->assertSame($assignments[0]['id_estudiante'], $after['cambios'][0]['id_estudiante']);
        $this->assertSame($saved['estudiantes'][0]['codigo_sis'], $after['cambios'][0]['cod_sis']);
        $this->assertSame($this->aulaId, $before['cambios'][0]['id_ambiente']);
        $this->assertSame($this->aulaId, $after['cambios'][0]['ambiente_anterior']);
        $this->assertSame($this->otraAulaId, $after['cambios'][0]['ambiente_nuevo']);
        $this->assertSame('GUARDAR_DISTRIBUCION', DB::table('accion')
            ->where('id_accion', $logs[1]->id_accion)->value('operacion'));
    }

    public function test_fallo_durante_insercion_restaura_toda_la_distribucion_anterior(): void
    {
        $exam = $this->distributionExam();
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $before = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);
        $assignments = $this->distributionProposal($exam);
        $studentId = $assignments[1]['id_estudiante'];
        DB::unprepared("
            CREATE FUNCTION hu031_fail_insert() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.id_estudiante = {$studentId} THEN
                    RAISE EXCEPTION 'fallo de insercion simulado';
                END IF;
                RETURN NEW;
            END \$\$;
            CREATE TRIGGER hu031_fail_insert BEFORE INSERT ON distribucion_ambiente
                FOR EACH ROW EXECUTE FUNCTION hu031_fail_insert();
        ");

        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertStatus(500);

        $this->assertSame($before, app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen));
        $this->assertSame(1, DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->count());
    }

    public function test_fallo_de_bitacora_tambien_revierte_el_reemplazo_de_asignaciones(): void
    {
        $exam = $this->distributionExam();
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $before = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);
        $assignments = $this->distributionProposal($exam);
        $assignments[0]['id_ambiente'] = $this->otraAulaId;
        DB::unprepared(<<<'SQL'
CREATE FUNCTION hu031_fail_audit() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.tabla_afectada = 'distribucion_ambiente' THEN
        RAISE EXCEPTION 'fallo de bitacora simulado';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER hu031_fail_audit BEFORE INSERT ON log
    FOR EACH ROW EXECUTE FUNCTION hu031_fail_audit();
SQL);

        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertStatus(500);

        $this->assertSame($before, app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen));
        $this->assertSame(1, DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->count());
    }

    public function test_reloj_congelado_conserva_microsegundos_y_versiones_sucesivas_distintas(): void
    {
        $exam = $this->distributionExam();
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00.123456+00'));

        try {
            $first = $this->saveDistribution($exam)->assertOk()->json('data');
            $second = $this->saveDistribution($exam)->assertOk()->json('data');
            $rows = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);

            $this->assertNotSame($first['version'], $second['version']);
            $this->assertStringContainsString('.123457', $rows[0]['asignado_en']);
            $this->assertSame($second['version'], DistributionVersion::fromRows($rows));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cero_habilitados_acepta_lista_vacia_y_audita_la_confirmacion(): void
    {
        $exam = $this->distributionExam(0);

        $this->putJson($this->distributionUrl($exam), [
            'version' => DistributionVersion::fromRows([]), 'asignaciones' => [],
        ])->assertOk()->assertJsonPath('data.version', DistributionVersion::fromRows([]))
            ->assertJsonPath('data.total_habilitados', 0)->assertJsonCount(0, 'data.estudiantes');
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
        $log = DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->first();
        $this->assertSame(0, json_decode($log->nuevo_valor, true)['total_cambios']);
    }

    public function test_deshabilitar_a_todos_limpia_la_distribucion_y_audita_los_retiros(): void
    {
        $exam = $this->distributionExam(2);
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');

        foreach ($saved['estudiantes'] as $student) {
            $this->setDistributionEligibility($exam, $student['id_estudiante'], 'NO_HABILITADO');
        }

        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => [],
        ])->assertOk()->assertJsonPath('data.version', DistributionVersion::fromRows([]));
        $this->assertSame(0, DB::table('distribucion_ambiente')->where('id_examen', $exam->id_examen)->count());
        $log = DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->orderByDesc('id_log')->first();
        $changes = json_decode($log->nuevo_valor, true)['cambios'];
        $this->assertCount(2, $changes);
        $this->assertSame([null, null], array_column($changes, 'ambiente_nuevo'));
    }

    public function test_guardar_otro_examen_no_modifica_la_clave_compuesta_del_primero(): void
    {
        $first = $this->distributionExam();
        $this->saveDistribution($first)->assertOk();
        $before = app(DistributionRosterQuery::class)->savedRows((int) $first->id_examen);
        $second = $this->createExam([], [$this->aulaId, $this->otraAulaId]);
        $groupId = DB::table('grupo_examen')->where('id_examen', $first->id_examen)->value('id_grupo');
        DB::table('grupo_examen')->insert(['id_examen' => $second->id_examen, 'id_grupo' => $groupId]);

        $this->saveDistribution($second)->assertOk();

        $this->assertSame($before, app(DistributionRosterQuery::class)->savedRows((int) $first->id_examen));
        $this->assertSame(8, DB::table('distribucion_ambiente')->count());
    }
}
