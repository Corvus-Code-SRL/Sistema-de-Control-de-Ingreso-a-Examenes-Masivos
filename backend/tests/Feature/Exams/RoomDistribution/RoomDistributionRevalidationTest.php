<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Services\Exams\Distribution\RoomDistributionSaveService;
use App\Services\Exams\Distribution\RoomDistributionQueryService;
use App\Support\Exams\DistributionMessages;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionRevalidationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_nuevo_habilitado_despues_de_la_propuesta_impide_guardar_una_nomina_incompleta(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $this->addDistributionStudent($exam);

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath('errors.asignaciones.0', DistributionMessages::INCOMPLETE_ROSTER);
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    public function test_reduccion_de_capacidad_despues_de_la_propuesta_se_revalida_al_guardar(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        DB::table('ambiente')->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])->update(['capacidad' => 1]);

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath(
            'errors.asignaciones.0', sprintf(DistributionMessages::MISSING_SEATS, 2)
        );
    }

    public function test_ambiente_retirado_despues_de_la_propuesta_no_se_puede_persistir(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $this->otraAulaId)->delete();
        DB::table('examen_ambiente')->insert(['id_examen' => $exam->id_examen, 'id_ambiente' => $this->aulaInactivaId]);

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath('errors.asignaciones.0', DistributionMessages::FOREIGN_ROOM);
    }

    public function test_cambio_de_creador_despues_de_la_consulta_no_conserva_permiso_de_escritura(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $exam->forceFill(['id_usuario_docente' => $this->otroDocenteId])->save();

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertForbidden();
    }

    public function test_los_bloqueos_y_la_escritura_se_mantienen_en_la_transaccion_del_service(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $outerLevel = DB::transactionLevel();
        $locked = [];
        $writes = 0;
        $levels = [];
        DB::listen(function (QueryExecuted $query) use (&$locked, &$writes, &$levels): void {
            if (strpos($query->sql, 'for update') !== false || strpos($query->sql, 'for share') !== false) {
                $locked[] = $query->sql;
                $levels[] = $query->connection->transactionLevel();
            }

            if (preg_match('/^(insert into|delete from) "(distribucion_ambiente|log)"/', $query->sql)) {
                $writes++;
                $levels[] = $query->connection->transactionLevel();
            }
        });

        app(RoomDistributionSaveService::class)->save((int) $exam->id_examen, $version, $assignments, $this->docenteId);

        foreach (['usuario', 'examen', 'grupo', 'grupo_estudiante', 'examen_estudiante',
            'examen_ambiente', 'ambiente', 'distribucion_ambiente'] as $table) {
            $this->assertNotEmpty(array_filter($locked, fn (string $sql): bool =>
                strpos($sql, 'from "' . $table . '"') !== false), "No se bloqueó {$table}.");
        }

        $this->assertSame(3, $writes);
        $this->assertStringContainsString('from "examen"', $locked[0]);
        $this->assertStringEndsWith('for update', $locked[0]);
        $this->assertStringContainsString('from "usuario"', $locked[1]);
        $this->assertStringEndsWith('for share', $locked[1]);
        $this->assertStringContainsString('from "ambiente"', $locked[2]);
        $this->assertStringEndsWith('for share', $locked[2]);
        $this->assertStringContainsString('from "examen_ambiente"', $locked[3]);
        $this->assertStringContainsString('from "grupo"', $locked[4]);
        $this->assertSame([$outerLevel + 1], array_values(array_unique($levels)));
        $this->assertSame($outerLevel, DB::transactionLevel());
    }

    public function test_la_propuesta_solo_bloquea_el_examen_en_lectura_y_no_escribe(): void
    {
        $exam = $this->distributionExam();
        $outerLevel = DB::transactionLevel();
        $locks = [];
        $writes = [];
        $levels = [];
        DB::listen(function (QueryExecuted $query) use (&$locks, &$writes, &$levels): void {
            if (strpos($query->sql, 'for update') !== false || strpos($query->sql, 'for share') !== false) {
                $locks[] = $query->sql;
                $levels[] = $query->connection->transactionLevel();
            }

            if (preg_match('/^(insert|update|delete)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $proposal = app(RoomDistributionQueryService::class)->proposal((int) $exam->id_examen, $this->docenteId);

        $this->assertCount(4, $proposal['estudiantes']);
        $this->assertCount(1, $locks);
        $this->assertStringContainsString('from "examen"', $locks[0]);
        $this->assertStringEndsWith('for share', $locks[0]);
        $this->assertSame([], $writes);
        $this->assertSame([$outerLevel + 1], $levels);
        $this->assertSame($outerLevel, DB::transactionLevel());
    }
}
