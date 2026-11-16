<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Ayudas para probar la rectificación de la Central de Riesgo (HU-062). Se usa junto con
 * SeedsRiskCenterRecords y SeedsSecurityAccounts: quien actúa es la cuenta autenticada en la prueba.
 */
trait SeedsRectifications
{
    protected function rectifyUrl(int $recordId): string
    {
        return "/api/rectificacion-riesgo/registros/{$recordId}/rectificar";
    }

    /** Pide rectificar por la API con un motivo válido, salvo lo que se indique en $overrides. */
    protected function rectify(int $recordId, array $overrides = []): TestResponse
    {
        return $this->postJson($this->rectifyUrl($recordId), array_merge([
            'motivo' => 'Se retira tras la reunión: el reporte no tenía sustento.',
        ], $overrides));
    }

    /**
     * Deja un registro ya rectificado sin pasar por la API, para probar lo que lo ignora. El administrador
     * es el de la prueba salvo que se indique otro.
     */
    protected function voidRecord(
        int $recordId,
        ?string $adminId = null,
        string $reason = 'Registro retirado por error de identificación.',
        string $at = '2026-10-20 15:00:00+00'
    ): void {
        DB::table('central_riesgo')->where('id_cr_estudiante', $recordId)->update([
            'rectificado_en' => $at,
            'id_usuario_rectificador' => $adminId ?? $this->administratorId,
            'motivo_rectificacion' => $reason,
        ]);
    }

    /** @return array<string, mixed> la fila cruda de central_riesgo, incluidas las columnas de rectificación */
    protected function recordRow(int $recordId): array
    {
        return (array) DB::table('central_riesgo')->where('id_cr_estudiante', $recordId)->first();
    }
}
