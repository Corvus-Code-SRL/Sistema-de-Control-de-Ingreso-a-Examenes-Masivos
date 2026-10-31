<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.convocatoria_admision.
 *
 * @property int $id_admision
 * @property int $id_facultad
 * @property int $id_gestion_periodo
 * @property string $nro_opciones
 * @property string $estado
 */
class AdmissionCall extends Model
{
    protected $table = 'convocatoria_admision';
    protected $primaryKey = 'id_admision';
    public $timestamps = false;

    protected $casts = [
        'id_facultad' => 'int',
        'id_gestion_periodo' => 'int',
    ];

    protected $fillable = [
        'id_facultad',
        'id_gestion_periodo',
        'nro_opciones',
        'estado'
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'id_facultad', 'id_facultad');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'id_gestion_periodo', 'id_gestion_periodo');
    }

    public function admissionExams(): HasMany
    {
        return $this->hasMany(AdmissionExam::class, 'id_admision', 'id_admision');
    }
}
