<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.sesion.
 *
 * @property int $id_sesion
 * @property string $id_usuario
 * @property int $pid
 * @property string $ip_direccion
 * @property int $num_puerto
 * @property \Carbon\Carbon $hora_ingreso
 */
class Session extends Model
{

    protected $table = 'sesion';
    // Recupera el IDENTITY. La PK SQL es compuesta: actualizar/eliminar por ambas claves con una consulta explícita.
    protected $primaryKey = 'id_sesion';
    public $timestamps = false;

    protected $casts = [
        'id_usuario' => 'string',
        'pid' => 'int',
        'ip_direccion' => 'string',
        'num_puerto' => 'int',
        'hora_ingreso' => 'datetime'
    ];

    protected $fillable = [
        'id_usuario',
        'pid',
        'ip_direccion',
        'num_puerto',
        'hora_ingreso'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
