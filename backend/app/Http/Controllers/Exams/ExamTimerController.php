<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ExamTimerRequest;
use App\Http\Resources\Exams\ExamTimerResource;
use App\Models\Exam;
use App\Services\Exams\ExamTimerService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class ExamTimerController extends Controller
{
    private ExamTimerService $timers;
    private CurrentUser $currentUser;

    public function __construct(ExamTimerService $timers, CurrentUser $currentUser)
    {
        $this->timers = $timers;
        $this->currentUser = $currentUser;
    }

    public function index(ExamTimerRequest $request): JsonResponse
    {
        $exams = $this->timers->listInProgress($this->currentUser->teacherId());

        return ApiResponse::success([
            'examenes' => ExamTimerResource::collection($exams),
            'hora_servidor_en' => now('UTC')->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(ExamTimerRequest $request, Exam $exam): JsonResponse
    {
        $this->timers->assertOwner($exam, $this->currentUser->teacherId());

        return $this->respond($exam);
    }

    public function save(ExamTimerRequest $request, Exam $exam): JsonResponse
    {
        return $this->respond($this->timers->save(
            $exam,
            $this->currentUser->teacherId(),
            (int) $request->validated()['minutos'],
            $request->isMethod('PUT')
        ));
    }

    private function respond(Exam $exam): JsonResponse
    {
        return ApiResponse::success([
            'examen' => new ExamTimerResource($exam),
            'hora_servidor_en' => now('UTC')->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }
}
