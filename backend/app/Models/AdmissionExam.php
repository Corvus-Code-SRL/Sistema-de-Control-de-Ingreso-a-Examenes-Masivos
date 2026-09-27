<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.examen_admision.
 *
 * @property int $id_admision
 * @property int $id_examen
 * @property string $nro_opcion
 */
class AdmissionExam extends Model
{
    protected $table = 'examen_admision';
    // UNIQUE NOT NULL en ambos scripts; no se genera automáticamente.
    protected $primaryKey = 'id_examen';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_admision' => 'int',
        'id_examen' => 'int'
    ];

    protected $fillable = [
        'id_admision',
        'id_examen',
        'nro_opcion'
    ];

    public function admissionCall(): BelongsTo
    {
        return $this->belongsTo(AdmissionCall::class, 'id_admision', 'id_admision');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }
}
