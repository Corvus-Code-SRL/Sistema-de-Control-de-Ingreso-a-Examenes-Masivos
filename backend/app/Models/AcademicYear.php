<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.gestion: el año académico.
 *
 * @property int $id_gestion
 * @property int $anio
 * @property string $estado
 */
class AcademicYear extends Model
{
    protected $table = 'gestion';
    protected $primaryKey = 'id_gestion';
    public $timestamps = false;

    protected $casts = [
        'anio' => 'int',
    ];

    protected $fillable = [
        'anio',
        'estado',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class, 'id_gestion', 'id_gestion');
    }
}
