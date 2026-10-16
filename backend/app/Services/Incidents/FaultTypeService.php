<?php

namespace App\Services\Incidents;

use App\Models\IncidentType;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta el catálogo de tipos de falta que comparten los reportes de estudiantes y de postulantes.
 */
class FaultTypeService
{
    /** Todos los tipos, ordenados por nombre: el catálogo no distingue activos de inactivos. */
    public function listAll(): Collection
    {
        return IncidentType::query()
            ->orderBy('nombre')
            ->get();
    }
}
