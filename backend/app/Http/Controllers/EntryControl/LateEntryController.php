<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryControl\AuthorizeLateEntryRequest;
use App\Http\Requests\EntryControl\IndexLateEntryRequest;
use App\Http\Requests\EntryControl\RegisterLateEntryRequest;
use App\Http\Requests\EntryControl\ResolveLateEntryRequest;
use App\Http\Requests\EntryControl\SearchLateEntryStudentRequest;
use App\Http\Requests\EntryControl\StoreLateEntryRequestRequest;
use App\Http\Requests\EntryControl\VerifyLateEntryRequest;
use App\Http\Resources\EntryControl\LateEntryRegistrationResource;
use App\Http\Resources\EntryControl\LateEntryRequestResource;
use App\Http\Resources\EntryControl\LateEntryStudentMatchResource;
use App\Http\Resources\EntryControl\LateEntryVerificationResource;
use App\Models\Exam;
use App\Services\EntryControl\LateEntryQueryService;
use App\Services\EntryControl\LateEntryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Ingreso con retraso de un examen EN_CURSO (HU-037): verificar, solicitar, autorizar,
 * rechazar y registrar directamente. Las reglas viven en LateEntryService.
 */
class LateEntryController extends Controller
{
    public const REQUESTED_MESSAGE = 'Solicitud enviada.';
    public const APPROVED_MESSAGE = 'Ingreso autorizado.';
    public const REJECTED_MESSAGE = 'Solicitud rechazada.';
    public const REGISTERED_MESSAGE = 'Ingreso con retraso registrado.';

    private LateEntryService $lateEntries;
    private LateEntryQueryService $queries;

    public function __construct(LateEntryService $lateEntries, LateEntryQueryService $queries)
    {
        $this->lateEntries = $lateEntries;
        $this->queries = $queries;
    }

    public function index(IndexLateEntryRequest $request, Exam $examen): JsonResponse
    {
        $list = $this->queries->list($examen, $request->user());

        return ApiResponse::success($list['solicitudes']->map(fn ($item) => new LateEntryRequestResource(
            $item,
            $list['otros_examenes'][(int) $item->id_estudiante] ?? null
        ))->values());
    }

    public function search(SearchLateEntryStudentRequest $request, Exam $examen): JsonResponse
    {
        return ApiResponse::success(LateEntryStudentMatchResource::collection(
            $this->queries->search($examen, $request->user(), $request->validated())
        ));
    }

    public function verify(VerifyLateEntryRequest $request, Exam $examen): JsonResponse
    {
        return ApiResponse::success(new LateEntryVerificationResource(
            $this->lateEntries->verify($examen, $request->validated(), $request->user())
        ));
    }

    public function store(StoreLateEntryRequestRequest $request, Exam $examen): JsonResponse
    {
        return ApiResponse::created(
            new LateEntryRequestResource(
                $this->lateEntries->request($examen, $request->validated(), $request->user())
            ),
            self::REQUESTED_MESSAGE
        );
    }

    public function approve(AuthorizeLateEntryRequest $request, Exam $examen, string $solicitud): JsonResponse
    {
        return ApiResponse::success(
            new LateEntryRequestResource(
                $this->lateEntries->authorize(
                    $examen,
                    (int) $solicitud,
                    $request->user(),
                    $request->boolean('confirmar_otro_examen')
                )
            ),
            self::APPROVED_MESSAGE
        );
    }

    public function reject(ResolveLateEntryRequest $request, Exam $examen, string $solicitud): JsonResponse
    {
        return ApiResponse::success(
            new LateEntryRequestResource(
                $this->lateEntries->reject($examen, (int) $solicitud, $request->user())
            ),
            self::REJECTED_MESSAGE
        );
    }

    public function register(RegisterLateEntryRequest $request, Exam $examen): JsonResponse
    {
        return ApiResponse::created(
            new LateEntryRegistrationResource(
                $this->lateEntries->register($examen, $request->validated(), $request->user())
            ),
            self::REGISTERED_MESSAGE
        );
    }
}
