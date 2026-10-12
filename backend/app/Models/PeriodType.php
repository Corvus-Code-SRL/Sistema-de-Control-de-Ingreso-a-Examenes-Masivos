<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.periodo: catálogo fijo de tipos de período (0 Anual, 1, 2, 3 Verano, 4 Invierno).
 *
 * El período concreto de una gestión (1/2026) es Period, que mapea gestion_periodo.
 *
 * @property int $id_periodo
 * @property int $codigo
 * @property string $nombre
 */
class PeriodType extends Model
{
    /** Códigos de los períodos numéricos, que se escriben «1/2026». */
    public const NUMERIC_CODES = [1, 2];

    protected $table = 'periodo';
    protected $primaryKey = 'id_periodo';
    public $timestamps = false;

    protected $casts = [
        'codigo' => 'int',
    ];

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class, 'id_periodo', 'id_periodo');
    }
}
