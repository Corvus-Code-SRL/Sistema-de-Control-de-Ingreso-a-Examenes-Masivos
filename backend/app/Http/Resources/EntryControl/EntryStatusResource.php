<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

/** Conserva las variantes de estado completo, sin cambios y apertura pendiente. */
class EntryStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
