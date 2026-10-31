<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Mapea public.grupo_auxiliar.
 *
 * Auxiliar incorporado a un grupo por el docente dueño. Un auxiliar puede
 * estar en varios grupos de varios docentes: quitar a un auxiliar de un grupo
 * solo marca INACTIVO esa fila, nunca borra.
 *
 * @property int $id_grupo
 * @property string $id_usuario
 * @property \Carbon\Carbon $fecha_incorporacion
 * @property string $estado
 */
class GroupAssistant extends Pivot
{
    protected $table = 'grupo_auxiliar';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_grupo' => 'int',
        'id_usuario' => 'string',
        'fecha_incorporacion' => 'date',
        'estado' => 'string',
    ];

    protected $fillable = [
        'id_grupo',
        'id_usuario',
        'fecha_incorporacion',
        'estado',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'id_grupo', 'id_grupo');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}