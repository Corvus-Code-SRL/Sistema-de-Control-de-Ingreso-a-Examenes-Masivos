<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EligibleListCurrentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'editable' => $this->resource->editable(),
            'estado' => $this->resource->estado(),
            'estudiantes' => $this->resource->estudiantes(),
        ];
    }
}
