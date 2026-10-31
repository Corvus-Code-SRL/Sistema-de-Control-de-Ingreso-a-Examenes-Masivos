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
    ];

    protected $fillable = [
        'id_accion',
        'antiguo_valor',
        'nuevo_valor',
        'fecha_hora',
        'tabla_afectada',
        'id_usuario'
    ];

    public function accion(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'id_accion', 'id_accion');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
