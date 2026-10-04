<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\IndexClassroomRequest;
use App\Http\Requests\Exams\StoreClassroomRequest;
use App\Http\Resources\Exams\ClassroomResource;
use App\Services\Exams\ClassroomService;
use App\Support\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller
{
    private ClassroomService $classroomService;

    private CurrentUser $currentUser;

    public function __construct(ClassroomService $classroomService, CurrentUser $currentUser)
    {
        $this->classroomService = $classroomService;
        $this->currentUser = $currentUser;
    }

    public function index(IndexClassroomRequest $request): AnonymousResourceCollection
    {
        return ClassroomResource::collection($this->classroomService->listAll());
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $classroom = $this->classroomService->registrar($request->validated(), $this->currentUser->id());

        return (new ClassroomResource($classroom))
            ->response()
            ->setStatusCode(201);
    }
}