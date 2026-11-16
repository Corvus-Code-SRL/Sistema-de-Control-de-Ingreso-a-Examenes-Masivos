<?php

namespace App\Models;

use App\Models\Scopes\ActiveRiskRecordScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.central_riesgo.
 *
 * Solo los reportes APROBADOS tienen fila aquí, y `id_estudiante` es el del reporte: la base no lo
 * impone, lo garantiza el servicio de aprobación de HU-045.
 *
 * Registros rectificados (HU-062): un administrador puede retirar un registro de la Central cuando se
 * concluye que no correspondía. La fila no se borra ni se reactiva; queda marcada con `rectificado_en`,
 * `id_usuario_rectificador` y `motivo_rectificacion` (las tres vacías o las tres con valor).
 *
 * Esta clase aplica {@see ActiveRiskRecordScope} como scope global: toda consulta Eloquent y toda relación
 * (`Student::riskCenters()`, `StudentReport::riskCenter()`) devuelve solo registros activos. Solo el módulo de
 * rectificación necesita ver los rectificados y lo pide con {@see self::withVoided()}. Las consultas SQL
 * crudas sobre central_riesgo no pasan por el scope y deben filtrar `rectificado_en IS NULL` a mano.
 *
 * @property int $id_cr_estudiante
 * @property int $id_reporte_est
 * @property int $id_estudiante
 * @property \Carbon\Carbon $fecha_registro
 * @property \Carbon\Carbon|null $rectificado_en
 * @property string|null $id_usuario_rectificador
 * @property string|null $motivo_rectificacion
 */
class RiskCenter extends Model
{
    protected $table = 'central_riesgo';
    protected $primaryKey = 'id_cr_estudiante';
    public $timestamps = false;

    protected $casts = [
        'id_reporte_est' => 'int',
        'id_estudiante' => 'int',
        'fecha_registro' => 'datetime',
        'rectificado_en' => 'datetime',
        'id_usuario_rectificador' => 'string',
    ];

    protected $fillable = [
        'id_reporte_est',
        'id_estudiante',
        'fecha_registro'
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveRiskRecordScope());
    }

    /** Consulta que incluye también los registros rectificados; solo para el módulo de rectificación. */
    public static function withVoided(): Builder
    {
        return static::query()->withoutGlobalScope(ActiveRiskRecordScope::class);
    }

    public function isRectified(): bool
    {
        return $this->rectificado_en !== null;
    }

    public function studentReport(): BelongsTo
    {
        return $this->belongsTo(StudentReport::class, 'id_reporte_est', 'id_reporte_est');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    /** Administrador que rectificó el registro; nulo mientras esté activo. */
    public function rectifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_rectificador', 'id_usuario');
    }
}
