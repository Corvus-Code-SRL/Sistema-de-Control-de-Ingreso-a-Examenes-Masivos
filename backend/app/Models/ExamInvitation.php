<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.invitacion_examen.
 *
 * @property int $id_invitacion
 * @property int $id_examen
 * @property string $id_docente_creador
 * @property string $id_docente_invitado
 * @property \Carbon\Carbon $fecha_invitacion
 * @property \Carbon\Carbon|null $fecha_respuesta
 * @property \Carbon\Carbon|null $fecha_sin_efecto
 * @property string $estado
 * @property bool $permite_agregar_grupos
 * @property bool $lista_por_grupo
 */
class ExamInvitation extends Model
{
    /** Valores de public.estado_invitacion. */
    public const PENDIENTE = 'PENDIENTE';
    public const ACEPTADA = 'ACEPTADA';
    public const RECHAZADA = 'RECHAZADA';
    public const SIN_EFECTO = 'SIN_EFECTO';

    /** Estados que impiden volver a invitar al mismo docente al mismo examen. */
    public const CURRENT_STATES = [self::PENDIENTE, self::ACEPTADA];

    protected $table = 'invitacion_examen';
    protected $primaryKey = 'id_invitacion';
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_docente_creador' => 'string',
        'id_docente_invitado' => 'string',
        'fecha_invitacion' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_sin_efecto' => 'datetime',
        'permite_agregar_grupos' => 'boolean',
        'lista_por_grupo' => 'boolean',
    ];

    protected $fillable = [
        'id_examen',
        'id_docente_creador',
        'id_docente_invitado',
        'fecha_invitacion',
        'fecha_respuesta',
        'fecha_sin_efecto',
        'estado',
        'permite_agregar_grupos',
        'lista_por_grupo',
    ];

    /** Invitaciones que todavía cuentan: pendientes o aceptadas. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('invitacion_examen.estado', self::CURRENT_STATES);
    }

    /**
     * Estado que se muestra: una invitación PENDIENTE cuyo examen ya no está PROGRAMADO está sin efecto aunque
     * el ciclo de vida todavía no la haya escrito así. Requiere el examen (se carga si falta).
     */
    public function effectiveState(): string
    {
        if ($this->estado === self::PENDIENTE && $this->exam->estado !== Exam::PROGRAMADO) {
            return self::SIN_EFECTO;
        }

        return $this->estado;
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function userInvited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente_invitado', 'id_usuario');
    }

    public function userCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente_creador', 'id_usuario');
    }
}
