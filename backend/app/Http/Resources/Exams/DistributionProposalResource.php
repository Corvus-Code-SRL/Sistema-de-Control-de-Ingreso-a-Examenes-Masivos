<?php

namespace App\Http\Resources\Exams;

use App\Services\Exams\Distribution\RoomDistributionQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @phpstan-import-type DistributionProposal from RoomDistributionQueryService */
class DistributionProposalResource extends JsonResource
{
    /**
     * @param Request $request
     * @return DistributionProposal
     */
    public function toArray($request): array
    {
        return [
            'estudiantes' => $this->resource['estudiantes'],
            'sin_asignar' => $this->resource['sin_asignar'],
        ];
    }
}
