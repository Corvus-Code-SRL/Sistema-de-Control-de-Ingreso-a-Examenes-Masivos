<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'log';
    protected $primaryKey = 'id_log';
    public $timestamps = false;

    protected $fillable = [
        'id_accion',
        'antiguo_valor',
        'nuevo_valor',
        'tabla_afectada',
        'id_usuario',
    ];

    /** jsonb <-> array de PHP. Sin esto tendrías que hacer json_encode a mano. */
    protected $casts = [
        'antiguo_valor' => 'array',
        'nuevo_valor'   => 'array',
        'fecha_hora'    => 'datetime',
    ];

    public function accion()
    {
        return $this->belongsTo(Action::class, 'id_accion', 'id_accion');
    }
}