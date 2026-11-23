<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.solicitud_rectificacion (HU-062).
 *
 * Una solicitud de rectificación la crea el Administrador y la responde el docente que aprobó el reporte. Las
 * rechazadas, aceptadas y vencidas quedan como historial del registro. Una PENDIENTE cuyo `vence_en` ya pasó se
 * trata como VENCIDA en toda lectura ({@see self::effectiveState()}), aunque la tarea programada todavía no la
 * haya marcado.
 *
 * @property int $id_solicitud_rect
 * @property int $id_cr_estudiante
 * @property string $id_usuario_solicitante
 * @property string $motivo_solicitud
 * @property string $id_usuario_docente
 * @property string $estado
 * @property Carbon $fecha_solicitud
 * @property Carbon $vence_en
 * @property Carbon|null $fecha_respuesta
 * @property string|null $motivo_rechazo
 * @property string|null $evidencia_public_id
 */
class RectificationRequest extends Model
{
    public const PENDING = 'PENDIENTE';
    public const ACCEPTED = 'ACEPTADA';
    public const REJECTED = 'RECHAZADA';
    public const EXPIRED = 'VENCIDA';

    protected $table = 'solicitud_rectificacion';
    protected $primaryKey = 'id_solicitud_rect';
    public $timestamps = false;

    protected $casts = [
        'id_cr_estudiante' => 'int',
        'id_usuario_solicitante' => 'string',
        'id_usuario_docente' => 'string',
        'fecha_solicitud' => 'datetime',
        'vence_en' => 'datetime',
        'fecha_respuesta' => 'datetime',
    ];

    protected $fillable = [
        'id_cr_estudiante',
        'id_usuario_solicitante',
        'motivo_solicitud',
        'id_usuario_docente',
        'estado',
        'fecha_solicitud',
        'vence_en',
        'fecha_respuesta',
        'motivo_rechazo',
        'evidencia_public_id',
    ];

    /** Estado que ve el resto del sistema: una pendiente vencida cuenta como VENCIDA. */
    public function effectiveState(?Carbon $now = null): string
    {
        if ($this->estado === self::PENDING && $this->vence_en->lessThanOrEqualTo($now ?? Carbon::now())) {
            return self::EXPIRED;
        }

        return $this->estado;
    }

    /** ¿Está pendiente y todavía dentro de su plazo? Es la única que el docente puede responder. */
    public function isAnswerable(?Carbon $now = null): bool
    {
        return $this->effectiveState($now) === self::PENDING;
    }

    /** Pendientes con plazo vigente. */
    public function scopeAnswerable(Builder $query): Builder
    {
        return $query->where('estado', self::PENDING)->where('vence_en', '>', Carbon::now());
    }

    /** El registro de la Central, incluso si ya fue rectificado (una solicitud aceptada lo anula). */
    public function record(): BelongsTo
    {
        return $this->belongsTo(RiskCenter::class, 'id_cr_estudiante', 'id_cr_estudiante')
            ->withoutGlobalScopes();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_solicitante', 'id_usuario');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_docente', 'id_usuario');
    }
}
