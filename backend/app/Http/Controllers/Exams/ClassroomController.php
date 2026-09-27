<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\StoreClassroomRequest;
use App\Http\Resources\Exams\ClassroomResource;
use App\Services\Exams\ClassroomService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller
{
    private ClassroomService $classroomService;

    public function __construct(ClassroomService $classroomService)
    {
        $this->classroomService = $classroomService;
    }

    public function index(): AnonymousResourceCollection
    {
        return ClassroomResource::collection($this->classroomService->listAll());
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $classroom = $this->classroomService->registrar($request->validated());

        return (new ClassroomResource($classroom))
            ->response()
            ->setStatusCode(201);
    }
}