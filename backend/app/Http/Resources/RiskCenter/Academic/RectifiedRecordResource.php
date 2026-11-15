<?php

namespace App\Http\Resources\RiskCenter\Academic;

/**
 * Un registro ya rectificado (retirado de la Central) en la lista «Registros rectificados» de solo lectura:
 * lo mismo que la lista activa más quién lo rectificó, cuándo y por qué.
 *
 * Requiere cargar además rectifier.
 */
class RectifiedRecordResource extends RectifiableRecordResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'rectificacion' => [
                'fecha' => $this->rectificado_en->toIso8601String(),
                'administrador' => ['nombre_completo' => $this->rectifier->nombre_completo ?? null],
                'motivo' => $this->motivo_rectificacion,
            ],
        ]);
    }
}
