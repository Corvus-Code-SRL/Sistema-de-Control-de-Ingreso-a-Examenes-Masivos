<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mapea public.reporte_postulante.
 *
 * @property int $id_reporte_post
 * @property int $id_postulante
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
class ApplicantReport extends Model
{
    protected $table = 'reporte_postulante';
    protected $primaryKey = 'id_reporte_post';
    public $timestamps = false;

    protected $casts = [
        'id_postulante' => 'int',
        'id_examen' => 'int',
        'id_tipo_falta' => 'int',
        'id_usuario_reportante' => 'string',
        'fecha_reporte' => 'datetime',
        'fecha_revision' => 'datetime',
        'id_usuario_revisor' => 'string'
    ];

    protected $fillable = [
        'id_postulante',
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

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'id_postulante', 'id_postulante');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class, 'id_tipo_falta', 'id_falta');
    }

    public function userReport(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_reportante', 'id_usuario');
    }

    public function userReview(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_revisor', 'id_usuario');
    }

    /** @deprecated Usar applicantRiskCenter(); colección de cero o una fila. */
    public function applicantRiskCenters(): HasMany
    {
        return $this->hasMany(ApplicantRiskCenter::class, 'id_reporte_post', 'id_reporte_post');
    }

    /** Un reporte puede tener como máximo una entrada en la central (UNIQUE). */
    public function applicantRiskCenter(): HasOne
    {
        return $this->hasOne(ApplicantRiskCenter::class, 'id_reporte_post', 'id_reporte_post');
    }
}
