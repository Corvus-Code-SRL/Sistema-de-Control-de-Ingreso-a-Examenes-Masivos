<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.sesion: una fila por inicio de sesión (un token de Sanctum).
 *
 * La PK SQL es compuesta (id_sesion, id_usuario); Eloquent usa id_sesion, que ya es único por ser IDENTITY.
 *
 * @property int $id_sesion
 * @property string $id_usuario
 * @property int|null $id_token
 * @property string $ip_direccion
 * @property string|null $agente
 * @property \Carbon\Carbon $hora_ingreso
 * @property \Carbon\Carbon|null $hora_salida
 * @property string|null $motivo_salida
 */
class Session extends Model
{
    /** La persona cerró la sesión. */
    public const CLOSED = 'CIERRE';

    /** El token venció sin que la persona cerrara. */
    public const EXPIRED = 'EXPIRADA';

    /** El sistema la cerró: cambio de contraseña o cuenta deshabilitada. */
    public const REVOKED = 'REVOCADA';

    protected $table = 'sesion';
    protected $primaryKey = 'id_sesion';
    public $timestamps = false;

    protected $casts = [
        'id_usuario' => 'string',
        'id_token' => 'int',
        'ip_direccion' => 'string',
        'agente' => 'string',
        'hora_ingreso' => 'datetime',
        'hora_salida' => 'datetime',
        'motivo_salida' => 'string',
    ];

    protected $fillable = [
        'id_usuario',
        'id_token',
        'ip_direccion',
        'agente',
        'hora_ingreso',
        'hora_salida',
        'motivo_salida',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
