<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mapea public.central_riesgo.
 *
 * Solo los reportes APROBADOS tienen fila aquí, y `id_estudiante` es el del reporte: la base no lo
 * impone, lo garantiza el servicio de aprobación de HU-045.
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

    /** Versiones de rectificación; vacía mientras el registro no se haya rectificado (HU-062). */
    public function versions(): HasMany
    {
        return $this->hasMany(RiskCenterVersion::class, 'id_cr_estudiante', 'id_cr_estudiante');
    }

    /**
     * Tipo de falta vigente: el de la versión vigente o, si nunca se rectificó, el del reporte.
     * Requiere cargar las relaciones.
     */
    public function effectiveIncidentType(): IncidentType
    {
        return $this->currentVersion !== null
            ? $this->currentVersion->incidentType
            : $this->studentReport->incidentType;
    }

    public function effectiveDescription(): ?string
    {
        return $this->currentVersion !== null
            ? $this->currentVersion->descripcion
            : $this->studentReport->descripcion;
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(RiskCenterVersion::class, 'id_cr_estudiante', 'id_cr_estudiante')
            ->where('es_vigente', true);
    }
}
