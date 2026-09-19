<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Orquestador principal de servicios para el módulo de exámenes.
 */
class ExamService
{
    protected ExamCreationService $creationService;
    protected ExamGroupService $groupService;
    protected ExamFormService $formService;

    public function __construct(
        ExamCreationService $creationService,
        ExamGroupService $groupService,
        ExamFormService $formService
    ) {
        $this->creationService = $creationService;
        $this->groupService    = $groupService;
        $this->formService     = $formService;
    }

    private function getCurrentTeacherId(): ?string
    {
        return auth()->id() ?? DB::table('usuario')->where('estado', 'ACTIVO')->value('id_usuario');
    }

    public function createExam(array $data): Exam
    {
        return $this->creationService->createExam($data);
    }

    public function assignGroups(Exam $exam, array $groupIds): Exam
    {
        return $this->groupService->assignGroups($exam, $groupIds);
    }

    public function getGroupsBySubject(int $subjectId)
    {
        $teacherId = $this->getCurrentTeacherId();
        return $this->groupService->getGroupsBySubject($subjectId, $teacherId);
    }

    public function getFormData()
    {
        $teacherId = $this->getCurrentTeacherId();
        return $this->formService->getFormData($teacherId);
    }
}