<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Migración 2026_11_17_000000 (HU-038): retira EN_INGRESO. Se quita el CHECK dentro de la transacción de la
 * prueba (el DDL de PostgreSQL es transaccional), se siembran exámenes con la forma ANTERIOR y se aplica la
 * migración. Todo se descarta al final.
 */
class RetireEstadoEnIngresoMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const CHECK = 'chk_examen_estado_sin_en_ingreso';

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_11_17_000000_retire_estado_en_ingreso_examen.php');
        $this->seedExamCatalog();
        DB::statement('ALTER TABLE public.examen DROP CONSTRAINT ' . self::CHECK);
    }

    private function legacyExam(string $state, array $dates = [], array $overrides = []): int
    {
        $exam = $this->createExam(array_merge(['estado' => $state], $overrides));
        if ($dates !== []) {
            DB::table('examen')->where('id_examen', $exam->id_examen)->update($dates);
        }

        return (int) $exam->id_examen;
    }

    /** @return array{estado: string, abierto: ?int, cerrado: ?int} */
    private function row(int $examId): array
    {
        $row = DB::table('examen')->where('id_examen', $examId)
            ->selectRaw('estado::text as estado')
            ->selectRaw('extract(epoch from ingreso_abierto_en)::bigint as abierto')
            ->selectRaw('extract(epoch from ingreso_ordinario_cerrado_en)::bigint as cerrado')
            ->first();

        return [
            'estado' => $row->estado,
            'abierto' => $row->abierto === null ? null : (int) $row->abierto,
            'cerrado' => $row->cerrado === null ? null : (int) $row->cerrado,
        ];
    }

    private function epoch(string $moment, ?string $zone = null): int
    {
        return Carbon::parse($moment, $zone ?? 'UTC')->getTimestamp();
    }

    private function migrate(): void
    {
        (new \RetireEstadoEnIngresoExamen())->up();
    }

    public function test_convierte_los_examenes_de_cada_estado_antiguo(): void
    {
        $zone = config('sciem.zona_horaria');
        $past = ['fecha' => '2026-01-10', 'hora_inicio' => '08:00'];

        $scheduled = $this->legacyExam(Exam::PROGRAMADO);
        $cancelled = $this->legacyExam(Exam::CANCELADO);
        $finishedNeverOpened = $this->legacyExam(Exam::FINALIZADO);

        // EN_INGRESO antes de su hora, con la apertura en la bitácora.
        $entryBefore = $this->legacyExam('EN_INGRESO', [], ['fecha' => $this->futureDate(10), 'hora_inicio' => '08:00']);
        app(AuditLogService::class)->registrar('MODIFICAR', 'examen', null, [
            'id_examen' => $entryBefore,
            'evento' => 'APERTURA_CONTROL',
        ], $this->docenteId);
        DB::table('log')->where('id_log', DB::table('log')->max('id_log'))
            ->update(['fecha_hora' => '2026-11-01 12:30:00+00']);

        // EN_INGRESO que ya pasó su hora y no tiene registro de la apertura: se calcula con minutos_apertura.
        $entryAfter = $this->legacyExam('EN_INGRESO', ['minutos_apertura' => 20], $past);

        // EN_INGRESO que ya tenía abierto (corrida parcial anterior): se conserva.
        $entryKept = $this->legacyExam(
            'EN_INGRESO',
            ['ingreso_abierto_en' => '2026-01-10 11:00:00+00'],
            $past
        );

        // En develop iniciar el examen cerraba el control: su ingreso ordinario no se reabre.
        $running = $this->legacyExam(Exam::EN_CURSO, ['inicio_real_en' => '2026-01-10 12:00:00+00'], $past);
        $finished = $this->legacyExam(Exam::FINALIZADO, [
            'inicio_real_en' => '2026-01-11 12:00:00+00',
            'fin_real_en' => '2026-01-11 13:30:00+00',
        ], ['fecha' => '2026-01-11', 'hora_inicio' => '08:00']);

        // Con el modelo nuevo ya usado: el FINALIZADO sin cierre cierra al terminar; lo ya cerrado no cambia.
        $finishedOpen = $this->legacyExam(Exam::FINALIZADO, [
            'ingreso_abierto_en' => '2026-01-12 11:30:00+00',
            'fin_real_en' => '2026-01-12 13:00:00+00',
        ], ['fecha' => '2026-01-12']);
        $runningClosed = $this->legacyExam(Exam::EN_CURSO, [
            'ingreso_abierto_en' => '2026-01-13 11:30:00+00',
            'ingreso_ordinario_cerrado_en' => '2026-01-13 12:15:00+00',
        ], ['fecha' => '2026-01-13']);

        $this->migrate();

        $this->assertSame(
            ['estado' => 'PROGRAMADO', 'abierto' => null, 'cerrado' => null],
            $this->row($scheduled)
        );
        $this->assertSame(
            ['estado' => 'CANCELADO', 'abierto' => null, 'cerrado' => null],
            $this->row($cancelled)
        );
        $this->assertSame(
            ['estado' => 'FINALIZADO', 'abierto' => null, 'cerrado' => null],
            $this->row($finishedNeverOpened)
        );

        $this->assertSame([
            'estado' => 'PROGRAMADO',
            'abierto' => $this->epoch('2026-11-01 12:30:00'),
            'cerrado' => null,
        ], $this->row($entryBefore));
        $this->assertSame([
            'estado' => 'EN_CURSO',
            'abierto' => $this->epoch('2026-01-10 07:40:00', $zone),
            'cerrado' => null,
        ], $this->row($entryAfter));
        $this->assertSame([
            'estado' => 'EN_CURSO',
            'abierto' => $this->epoch('2026-01-10 11:00:00'),
            'cerrado' => null,
        ], $this->row($entryKept));

        $startedAt = $this->epoch('2026-01-10 12:00:00');
        $this->assertSame(
            ['estado' => 'EN_CURSO', 'abierto' => $startedAt, 'cerrado' => $startedAt],
            $this->row($running)
        );
        $finishedStart = $this->epoch('2026-01-11 12:00:00');
        $this->assertSame(
            ['estado' => 'FINALIZADO', 'abierto' => $finishedStart, 'cerrado' => $finishedStart],
            $this->row($finished)
        );
        $this->assertSame([
            'estado' => 'FINALIZADO',
            'abierto' => $this->epoch('2026-01-12 11:30:00'),
            'cerrado' => $this->epoch('2026-01-12 13:00:00'),
        ], $this->row($finishedOpen));
        $this->assertSame([
            'estado' => 'EN_CURSO',
            'abierto' => $this->epoch('2026-01-13 11:30:00'),
            'cerrado' => $this->epoch('2026-01-13 12:15:00'),
        ], $this->row($runningClosed));
    }

    public function test_despues_de_migrar_ningun_examen_queda_en_ingreso(): void
    {
        $this->legacyExam('EN_INGRESO', [], ['fecha' => $this->futureDate(5)]);
        $this->legacyExam('EN_INGRESO', [], ['fecha' => '2026-01-10']);

        $this->migrate();

        $this->assertSame(0, DB::table('examen')->whereRaw("estado::text = 'EN_INGRESO'")->count());
    }

    public function test_es_idempotente(): void
    {
        $this->legacyExam('EN_INGRESO', [], ['fecha' => $this->futureDate(5)]);
        $this->legacyExam(Exam::EN_CURSO, ['inicio_real_en' => '2026-01-10 12:00:00+00'], ['fecha' => '2026-01-10']);

        $this->migrate();
        $afterFirst = DB::table('examen')->orderBy('id_examen')->get()->toJson();

        $this->migrate();

        $this->assertSame($afterFirst, DB::table('examen')->orderBy('id_examen')->get()->toJson());
    }

    public function test_no_reabre_ni_recierra_un_examen_que_ya_uso_el_modelo_nuevo(): void
    {
        $this->migrate();

        // Con el CHECK ya creado la migración no toca nada: un EN_CURSO con el ingreso abierto sigue abierto.
        $running = $this->createExam(array_merge(['estado' => Exam::EN_CURSO], $this->entryOpen()));
        $this->migrate();

        $this->assertNull($this->row((int) $running->id_examen)['cerrado']);
    }

    public function test_el_check_impide_asignar_en_ingreso_y_down_lo_quita(): void
    {
        $examId = $this->legacyExam(Exam::PROGRAMADO);
        $this->migrate();

        try {
            DB::transaction(fn () => DB::table('examen')->where('id_examen', $examId)->update(['estado' => 'EN_INGRESO']));
            $this->fail('El CHECK debía rechazar EN_INGRESO.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(self::CHECK, $exception->getMessage());
        }
        $this->assertSame('PROGRAMADO', $this->row($examId)['estado']);

        (new \RetireEstadoEnIngresoExamen())->down();

        DB::table('examen')->where('id_examen', $examId)->update(['estado' => 'EN_INGRESO']);
        $this->assertSame('EN_INGRESO', $this->row($examId)['estado']);
    }
}
