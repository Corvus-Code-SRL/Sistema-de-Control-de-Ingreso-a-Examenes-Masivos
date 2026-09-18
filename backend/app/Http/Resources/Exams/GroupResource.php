<?php
namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_grupo' => $this->id_grupo,
            'num_grupo' => $this->num_grupo,
            'gestion' => $this->gestion,
            'estado' => $this->estado,
        ];
    }
}
