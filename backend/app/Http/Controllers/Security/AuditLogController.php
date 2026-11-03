<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\ListAuditLogRequest;
use App\Http\Requests\Security\ShowAuditLogRequest;
use App\Http\Resources\Security\AuditLogDetailResource;
use App\Http\Resources\Security\AuditLogOptionsResource;
use App\Http\Resources\Security\AuditLogResource;
use App\Services\Security\AuditLogQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Consulta de solo lectura de la bitácora (HU-015). No hay operaciones de escritura ni se audita la consulta. */
class AuditLogController extends Controller
{
    private AuditLogQueryService $logs;

    public function __construct(AuditLogQueryService $logs)
    {
        $this->logs = $logs;
    }

    /** GET /api/bitacora */
    public function index(ListAuditLogRequest $request): AnonymousResourceCollection
    {
        return AuditLogResource::collection($this->logs->paginate($request->filters()));
    }

    /** GET /api/bitacora/opciones */
    public function options(ShowAuditLogRequest $request): AuditLogOptionsResource
    {
        return new AuditLogOptionsResource($this->logs->options());
    }

    /** GET /api/bitacora/{id} */
    public function show(ShowAuditLogRequest $request, int $id): AuditLogDetailResource
    {
        return new AuditLogDetailResource($this->logs->find($id));
    }
}
