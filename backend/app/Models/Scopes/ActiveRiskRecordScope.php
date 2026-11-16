<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Deja fuera de toda lectura Eloquent de la Central de Riesgo los registros rectificados (HU-062).
 *
 * Un registro rectificado fue retirado de la Central y ya no cuenta como antecedente. Por eso `RiskCenter` aplica
 * este scope siempre; solo el módulo de rectificación lo quita con `RiskCenter::withVoided()`.
 *
 * Las consultas SQL crudas sobre central_riesgo no pasan por aquí y deben filtrar `rectificado_en IS NULL`.
 */
class ActiveRiskRecordScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn('rectificado_en'));
    }
}
