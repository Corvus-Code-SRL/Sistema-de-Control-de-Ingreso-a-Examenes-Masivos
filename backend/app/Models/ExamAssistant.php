<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Mapea public.examen_auxiliar.
 *
 * Auxiliar habilitado para un examen. A diferencia de grupo_auxiliar, esta
 * tabla no tiene estado: quitar un auxiliar de un examen borra la fila.
 *
 * id_ambiente lo asigna HU-09; aquí se deja NULL al habilitar.
 *
 * @property int $id_examen
 * @property string $id_usuario
 * @property string $id_usuario_docente_habilita
 * @property \Carbon\Carbon $fecha_habilitacion
 * @property int|null $id_ambiente
 */
class ExamAssistant extends Pivot
{
    protected $table = 'examen_auxiliar';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_usuario' => 'string',
        'id_usuario_docente_habilita' => 'string',
        'fecha_habilitacion' => 'datetime',
        'id_ambiente' => 'int',
    ];

    protected $fillable = [
        'id_examen',
        'id_usuario',
        'id_usuario_docente_habilita',
        'fecha_habilitacion',
        'id_ambiente',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_docente_habilita', 'id_usuario');
    }
}