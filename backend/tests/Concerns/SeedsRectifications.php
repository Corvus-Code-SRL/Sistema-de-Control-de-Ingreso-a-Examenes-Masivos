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

    /** Pide la rectificación por la API con valores válidos, salvo lo que se indique en $overrides. */
    protected function rectify(int $recordId, array $overrides = []): TestResponse
    {
        return $this->postJson($this->rectifyUrl($recordId), array_merge([
            'id_tipo_falta' => $this->insertFaultType('Otra falta ' . uniqid()),
            'descripcion' => 'Descripción corregida.',
            'motivo' => 'Se corrige tras revisar la evidencia.',
            'version_base' => 1,
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> versiones del registro, de la más antigua a la más nueva */
    protected function versionsOf(int $recordId): array
    {
        return DB::table('central_riesgo_version')
            ->where('id_cr_estudiante', $recordId)
            ->orderBy('numero_version')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
