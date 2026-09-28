<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mapea public.reporte_estudiante.
 *
 * @property int $id_reporte_est
 * @property int $id_estudiante
 * @property int $id_examen
 * @property int $id_tipo_falta
 * @property string $id_usuario_reportante
 * @property string|null $descripcion
 * @property string $evidencia_url
 * @property string $evidencia_public_id
 * @property \Carbon\Carbon $fecha_reporte
 * @property string $estado
 * @property \Carbon\Carbon|null $fecha_revision
 * @property string|null $observacion_revision
 * @property string|null $id_usuario_revisor
 */
class StudentReport extends Model
{
    protected $table = 'reporte_estudiante';
    protected $primaryKey = 'id_reporte_est';
    public $timestamps = false;

    protected $casts = [
        'id_estudiante' => 'int',
        'id_examen' => 'int',
        'id_tipo_falta' => 'int',
        'id_usuario_reportante' => 'string',
        'fecha_reporte' => 'datetime',
        'fecha_revision' => 'datetime',
        'id_usuario_revisor' => 'string'
    ];

    protected $fillable = [
        'id_estudiante',
        'id_examen',
        'id_tipo_falta',
        'id_usuario_reportante',
        'descripcion',
        'evidencia_url',
        'evidencia_public_id',
        'fecha_reporte',
        'estado',
        'fecha_revision',
        'observacion_revision',
        'id_usuario_revisor'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class, 'id_tipo_falta', 'id_falta');
    }

    public function userReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_revisor', 'id_usuario');
    }

    public function userReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_reportante', 'id_usuario');
    }

    /** @deprecated Usar riskCenter(); colección de cero o una fila. */
    public function riskCenters(): HasMany
    {
        return $this->hasMany(RiskCenter::class, 'id_reporte_est', 'id_reporte_est');
    }

    /** Un reporte puede tener como máximo una entrada en la central (UNIQUE). */
    public function riskCenter(): HasOne
    {
        return $this->hasOne(RiskCenter::class, 'id_reporte_est', 'id_reporte_est');
    }
}
