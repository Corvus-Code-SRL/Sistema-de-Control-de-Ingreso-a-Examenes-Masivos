<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Exam::$dateFormat lleva el offset explícito para que las columnas timestamptz no dependan de la zona
 * de la sesión de la base. Se comprueba que cada atributo de fecha del examen se guarda y se lee igual con
 * la sesión en UTC y en America/La_Paz.
 */
class ExamDateAttributesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    /** @return array<string, array{0: string}> */
    public function sessionZones(): array
    {
        return ['UTC' => ['UTC'], 'America/La_Paz' => ['America/La_Paz']];
    }

    /** @dataProvider sessionZones */
    public function test_cada_atributo_de_fecha_se_guarda_y_se_lee_igual_en_cualquier_zona_de_sesion(string $zone): void
    {
        $this->seedExamCatalog();
        $original = DB::selectOne('show timezone')->TimeZone;
        DB::statement("set time zone '{$zone}'");

        try {
            $laPaz = config('sciem.zona_horaria');
            $opened = Carbon::parse('2026-11-20 07:40:00', $laPaz);
            $closed = Carbon::parse('2026-11-20 08:15:00', $laPaz);
            $finished = Carbon::parse('2026-11-20 09:30:00', $laPaz);

            $exam = $this->createExam(['fecha' => '2026-11-20', 'hora_inicio' => '08:00']);
            $exam->ingreso_abierto_en = $opened;
            $exam->ingreso_ordinario_cerrado_en = $closed;
            $exam->fin_real_en = $finished;
            $exam->save();

            $fresh = Exam::query()->findOrFail($exam->id_examen);

            $this->assertSame('2026-11-20', $fresh->fecha->toDateString());
            $this->assertSame($opened->getTimestamp(), $fresh->ingreso_abierto_en->getTimestamp());
            $this->assertSame($closed->getTimestamp(), $fresh->ingreso_ordinario_cerrado_en->getTimestamp());
            $this->assertSame($finished->getTimestamp(), $fresh->fin_real_en->getTimestamp());

            $epochs = DB::table('examen')->where('id_examen', $exam->id_examen)
                ->selectRaw('extract(epoch from ingreso_abierto_en)::bigint as a')
                ->selectRaw('extract(epoch from ingreso_ordinario_cerrado_en)::bigint as c')
                ->selectRaw('extract(epoch from fin_real_en)::bigint as f')
                ->first();
            $this->assertSame([$opened->getTimestamp(), $closed->getTimestamp(), $finished->getTimestamp()], [
                (int) $epochs->a,
                (int) $epochs->c,
                (int) $epochs->f,
            ]);

            $fresh->save();
            $this->assertFalse($fresh->isDirty());
            $this->assertSame($opened->getTimestamp(), $fresh->fresh()->ingreso_abierto_en->getTimestamp());
        } finally {
            DB::statement("set time zone '{$original}'");
        }
    }

    /** @dataProvider sessionZones */
    public function test_la_fecha_del_examen_no_cambia_de_dia_segun_la_zona_de_la_sesion(string $zone): void
    {
        $this->seedExamCatalog();
        $original = DB::selectOne('show timezone')->TimeZone;
        DB::statement("set time zone '{$zone}'");

        try {
            $exam = $this->createExam(['fecha' => '2026-11-20']);
            $exam->fecha = Carbon::parse('2026-11-21', config('sciem.zona_horaria'));
            $exam->save();

            $this->assertSame('2026-11-21', Exam::query()->findOrFail($exam->id_examen)->fecha->toDateString());
            $this->assertSame('2026-11-21', DB::table('examen')->where('id_examen', $exam->id_examen)->value('fecha'));
        } finally {
            DB::statement("set time zone '{$original}'");
        }
    }

    /** @dataProvider sessionZones */
    public function test_la_apertura_con_el_servicio_queda_en_el_instante_correcto(string $zone): void
    {
        $this->seedExamCatalog();
        $original = DB::selectOne('show timezone')->TimeZone;
        DB::statement("set time zone '{$zone}'");

        try {
            $exam = $this->createExam(['fecha' => $this->futureDate(), 'hora_inicio' => '08:00'], [$this->aulaId]);
            DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
            $before = Carbon::now()->getTimestamp();

            app(\App\Services\EntryControl\RoomAssignmentService::class)->prepare((int) $exam->id_examen);

            $openedAt = Exam::query()->findOrFail($exam->id_examen)->ingreso_abierto_en->getTimestamp();
            $this->assertGreaterThanOrEqual($before - 2, $openedAt);
            $this->assertLessThanOrEqual(Carbon::now()->getTimestamp() + 2, $openedAt);
        } finally {
            DB::statement("set time zone '{$original}'");
        }
    }
}
