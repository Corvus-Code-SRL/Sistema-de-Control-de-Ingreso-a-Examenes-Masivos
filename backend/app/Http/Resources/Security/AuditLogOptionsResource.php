<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/** Valores de los selectores de acción y módulo de la bitácora. */
class AuditLogOptionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'acciones' => $this->resource['acciones'],
            'modulos' => $this->resource['modulos'],
        ];
    }
}
