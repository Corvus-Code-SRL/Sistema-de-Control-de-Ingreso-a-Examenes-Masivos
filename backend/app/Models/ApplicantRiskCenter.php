<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.central_riesgo_postulante.
 *
 * @property int $id_cr_postulante
 * @property int $id_reporte_post
 * @property int $id_postulante
 * @property \Carbon\Carbon $fecha_registro
 */
class ApplicantRiskCenter extends Model
{
    protected $table = 'central_riesgo_postulante';
    protected $primaryKey = 'id_cr_postulante';
    public $timestamps = false;

    protected $casts = [
        'id_reporte_post' => 'int',
        'id_postulante' => 'int',
        'fecha_registro' => 'datetime'
    ];

    protected $fillable = [
        'id_reporte_post',
        'id_postulante',
        'fecha_registro'
    ];

    public function applicantReport(): BelongsTo
    {
        return $this->belongsTo(ApplicantReport::class, 'id_reporte_post', 'id_reporte_post');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'id_postulante', 'id_postulante');
    }
}
