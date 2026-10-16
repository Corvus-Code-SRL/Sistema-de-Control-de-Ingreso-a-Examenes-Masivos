<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\IndexFaultTypeRequest;
use App\Http\Resources\Incidents\FaultTypeResource;
use App\Services\Incidents\FaultTypeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaultTypeController extends Controller
{
    private FaultTypeService $faultTypeService;

    public function __construct(FaultTypeService $faultTypeService)
    {
        $this->faultTypeService = $faultTypeService;
    }

    public function index(IndexFaultTypeRequest $request): AnonymousResourceCollection
    {
        return FaultTypeResource::collection($this->faultTypeService->listAll());
    }
}
