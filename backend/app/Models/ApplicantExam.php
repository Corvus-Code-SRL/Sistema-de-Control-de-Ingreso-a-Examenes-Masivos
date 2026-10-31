<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.examen_postulante.
 *
 * @property int $id_examen
 * @property int $id_postulante
 * @property string $estado_habilitacion
 * @property string $estado_ingreso
 * @property string|null $hora_ingreso
 */
class ApplicantExam extends Pivot
{

    protected $table = 'examen_postulante';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_postulante' => 'int',
        'hora_ingreso' => 'string'
    ];

    protected $fillable = [
        'id_examen',
        'id_postulante',
        'estado_habilitacion',
        'estado_ingreso',
        'hora_ingreso',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'id_postulante', 'id_postulante');
    }
}
