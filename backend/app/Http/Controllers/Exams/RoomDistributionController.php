<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ListDistributionExamsRequest;
use App\Http\Requests\Exams\SaveRoomDistributionRequest;
use App\Http\Requests\Exams\ShowRoomDistributionRequest;
use App\Http\Resources\Exams\DistributionExamOptionResource;
use App\Http\Resources\Exams\DistributionProposalResource;
use App\Http\Resources\Exams\RoomDistributionResource;
use App\Services\Exams\Distribution\RoomDistributionQueryService;
use App\Services\Exams\Distribution\RoomDistributionSaveService;
use App\Services\Exams\Distribution\RoomDistributionStopService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\Exams\DistributionMessages;
use Illuminate\Http\JsonResponse;

class RoomDistributionController extends Controller
{
    public function __construct(
        private RoomDistributionQueryService $query,
        private RoomDistributionSaveService $save,
        private RoomDistributionStopService $stopper,
        private CurrentUser $currentUser
    ) {
    }

    public function exams(ListDistributionExamsRequest $request): JsonResponse
    {
        return ApiResponse::success(DistributionExamOptionResource::collection(
            $this->query->exams($this->currentUser->teacherId())
        ));
    }

    public function show(ShowRoomDistributionRequest $request, int $exam): JsonResponse
    {
        return ApiResponse::success(new RoomDistributionResource(
            $this->query->current($exam, $this->currentUser->teacherId())
        ));
    }

    public function proposal(ShowRoomDistributionRequest $request, int $exam): JsonResponse
    {
        return ApiResponse::success(new DistributionProposalResource(
            $this->query->proposal($exam, $this->currentUser->teacherId())
        ));
    }

    public function save(SaveRoomDistributionRequest $request, int $exam): JsonResponse
    {
        $result = $this->save->save(
            $exam,
            $request->validated()['version'],
            $request->assignments(),
            $this->currentUser->teacherId()
        );

        return ApiResponse::success(new RoomDistributionResource($result), DistributionMessages::SAVED);
    }

    public function stop(ShowRoomDistributionRequest $request, int $exam): JsonResponse
    {
        return ApiResponse::success(
            new RoomDistributionResource($this->stopper->stop($exam, $this->currentUser->teacherId())),
            DistributionMessages::STOPPED
        );
    }
}
