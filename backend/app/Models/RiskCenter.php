<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.central_riesgo.
 *
 * @property int $id_cr_estudiante
 * @property int $id_reporte_est
 * @property int $id_estudiante
 * @property \Carbon\Carbon $fecha_registro
 */
class RiskCenter extends Model
{
    protected $table = 'central_riesgo';
    protected $primaryKey = 'id_cr_estudiante';
    public $timestamps = false;

    protected $casts = [
        'id_reporte_est' => 'int',
        'id_estudiante' => 'int',
        'fecha_registro' => 'datetime'
    ];

    protected $fillable = [
        'id_reporte_est',
        'id_estudiante',
        'fecha_registro'
    ];

    public function studentReport(): BelongsTo
    {
        return $this->belongsTo(StudentReport::class, 'id_reporte_est', 'id_reporte_est');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }
}
