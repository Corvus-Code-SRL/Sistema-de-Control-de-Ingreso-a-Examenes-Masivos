<?php

namespace Tests\Feature\Academic;

use App\Exceptions\Academic\StudentRosterGroupAccessException;
use App\Models\Exam;
use App\Models\Group;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Services\Exams\ExamRosterLockService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StudentRosterGroupAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    public function test_devuelve_grupo_propio_activo_del_periodo_actual(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $result = $this->access()->getAvailable(
            (int) $group->id_grupo
        );

        $this->assertSame(
            $group->id_grupo,
            $result->id_grupo
        );
    }

    public function test_falla_si_el_grupo_no_existe(): void
    {
        $this->seedAcademicCatalog();

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->access()->getAvailable(999999);
    }

    public function test_rechaza_grupo_de_otro_docente(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->otroDocenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->firstOrFail();

        $this->assertRejectedWith(
            403,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    public function test_rechaza_grupo_inactivo(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $group->estado = RecordStatus::INACTIVE;
        $group->save();

        $this->assertRejectedWith(
            422,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    public function test_rechaza_grupo_de_otro_periodo(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoAnteriorId
            )
            ->firstOrFail();

        $this->assertRejectedWith(
            422,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    /**
     * @dataProvider lockingExamStates
     */
    public function test_rechaza_el_grupo_con_un_examen_en_ingreso_o_en_curso(string $state): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $this->linkExam($group, $state);

        try {
            $this->access()->getAvailable((int) $group->id_grupo);

            $this->fail('El grupo con un examen en ' . $state . ' debía rechazarse.');
        } catch (StudentRosterGroupAccessException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('en ingreso o en curso', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function lockingExamStates(): array
    {
        return [
            'EN_INGRESO' => [Exam::EN_INGRESO],
            'EN_CURSO' => [Exam::EN_CURSO],
        ];
    }

    /**
     * @dataProvider freeExamStates
     */
    public function test_permite_el_grupo_si_sus_examenes_no_estan_en_ingreso_ni_en_curso(string $state): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $this->linkExam($group, $state);

        $this->assertSame(
            (int) $group->id_grupo,
            (int) $this->access()->getAvailable((int) $group->id_grupo)->id_grupo
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function freeExamStates(): array
    {
        return [
            'PROGRAMADO' => [Exam::PROGRAMADO],
            'FINALIZADO' => [Exam::FINALIZADO],
            'CANCELADO' => [Exam::CANCELADO],
        ];
    }

    public function test_solo_bloquea_el_grupo_del_examen_en_curso_y_no_otros_grupos(): void
    {
        $this->seedExamCatalog();

        $group = $this->ownActiveGroup();
        $otherGroupId = (int) Group::query()
            ->where('id_usuario_docente', $this->docenteId)
            ->where('id_periodo', $this->periodoActivoId)
            ->where('id_grupo', '!=', $group->id_grupo)
            ->value('id_grupo');

        $this->linkExam($group, Exam::EN_CURSO);

        $this->assertSame(
            $otherGroupId,
            (int) $this->access()->getAvailable($otherGroupId)->id_grupo
        );
    }

    private function linkExam(Group $group, string $state): void
    {
        $exam = $this->createExam(['estado' => $state]);

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $group->id_grupo,
        ]);
    }

    private function access(): StudentRosterGroupAccess
    {
        return new StudentRosterGroupAccess(new ExamRosterLockService());
    }

    private function ownActiveGroup(): Group
    {
        return Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->where(
                'estado',
                RecordStatus::ACTIVE
            )
            ->firstOrFail();
    }

    private function assertRejectedWith(
        int $statusCode,
        callable $operation
    ): void {
        try {
            $operation();

            $this->fail(
                'La operación debía rechazar el grupo.'
            );
        } catch (
            StudentRosterGroupAccessException $exception
        ) {
            $this->assertSame(
                $statusCode,
                $exception->getStatusCode()
            );
        }
    }
}