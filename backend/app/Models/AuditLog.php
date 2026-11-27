<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.log.
 *
 * @property int $id_log
 * @property int|null $id_accion
 * @property array|null $antiguo_valor
 * @property array|null $nuevo_valor
 * @property \Carbon\Carbon $fecha_hora
 * @property string $tabla_afectada
 * @property string $id_usuario
 * @property int|null $id_sesion sesión (dispositivo) desde la que se hizo; null sin sesión
 */
class AuditLog extends Model
{
    protected $table = 'log';
    protected $primaryKey = 'id_log';
    public $timestamps = false;

    protected $casts = [
        'id_accion' => 'integer',
        'id_usuario' => 'string',
        'antiguo_valor' => 'array',
        'nuevo_valor' => 'array',
        'fecha_hora' => 'datetime',
        'id_sesion' => 'integer',
    ];

    protected $fillable = [
        'id_accion',
        'antiguo_valor',
        'nuevo_valor',
        'fecha_hora',
        'tabla_afectada',
        'id_usuario',
        'id_sesion',
    ];

    public function accion(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'id_accion', 'id_accion');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'id_sesion', 'id_sesion');
    }
}
