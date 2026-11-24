<?php

namespace Tests\Feature\EntryControl;

use App\Models\StudentExam;
use App\Services\EntryControl\RoomOccupancyService;
use App\Services\Exams\ExamRosterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomOccupancyServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    public function test_cuenta_presentes_sin_retirados_ni_pendientes(): void
    {
        $this->seedExamCatalog();
        $exam = $this->distributionExam(4, [5, 5]);
        $examId = (int) $exam->id_examen;
        [$onTime, $late, $expelled, $pending] = $this->distributionStudentIds($exam);
        $groupId = (int) DB::table('grupo_examen')->where('id_examen', $examId)->value('id_grupo');

        foreach ([$onTime, $late, $expelled, $pending] as $studentId) {
            app(ExamRosterService::class)->ensureRow($examId, $studentId, $groupId);
        }

        $row = fn (int $id) => DB::table('examen_estudiante')->where('id_examen', $examId)->where('id_estudiante', $id);
        $entered = ['hora_ingreso' => '10:00:00', 'id_ambiente' => $this->aulaId];
        $row($onTime)->update($entered + ['estado_ingreso' => StudentExam::INGRESO]);
        $row($late)->update($entered + ['estado_ingreso' => StudentExam::CON_RETRASO]);
        $row($expelled)->update($entered + ['estado_ingreso' => StudentExam::INGRESO, 'expulsado_en' => now()]);
        $row($pending)->update(['id_ambiente' => $this->aulaId]);

        $this->assertSame(
            ['nro_aula' => '691A', 'capacidad' => 5, 'ocupados' => 2],
            app(RoomOccupancyService::class)->forRoom($examId, $this->aulaId)
        );
    }
}
