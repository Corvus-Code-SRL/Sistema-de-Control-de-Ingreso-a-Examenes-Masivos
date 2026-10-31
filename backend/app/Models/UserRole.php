<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.usuario_rol.
 *
 * @property string $id_usuario
 * @property int $id_rol
 * @property \Carbon\Carbon $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 */
class UserRole extends Pivot
{

    protected $table = 'usuario_rol';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_usuario' => 'string',
        'id_rol' => 'int',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime'
    ];

    protected $fillable = [
        'id_usuario',
        'id_rol',
        'fecha_inicio',
        'fecha_fin',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }
}
