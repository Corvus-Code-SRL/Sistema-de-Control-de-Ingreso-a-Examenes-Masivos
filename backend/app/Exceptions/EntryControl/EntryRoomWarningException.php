<?php

namespace App\Exceptions\EntryControl;

use App\Support\ApiResponse;
use App\Support\Exams\EntryRoomWarning;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** El registro tiene un aviso de ambiente sin confirmar: viaja para que el cliente lo muestre. */
class EntryRoomWarningException extends Exception
{
    /** @param list<array{tipo:string, mensaje:string}> $warnings */
    public function __construct(private array $warnings)
    {
        parent::__construct(EntryRoomWarning::CONFIRM_MESSAGE);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409, [
            'veredicto' => EntryRoomWarning::VERDICT,
            'avisos_ambiente' => $this->warnings,
        ]);
    }
}
