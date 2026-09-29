<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Mapea public.examen_auxiliar: auxiliar habilitado para un examen (HU-08) y el
 * ambiente donde controla el ingreso (HU-09).
 *
 * La clave es compuesta y el modelo no la conoce, así que las modificaciones se hacen
 * con consultas que filtran por examen y auxiliar (scope forExamAndAssistant).
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

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_docente_habilita', 'id_usuario');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente', 'id_ambiente');
    }

    public function scopeForExamAndAssistant(Builder $query, int $examId, string $userId): Builder
    {
        return $query->where('id_examen', $examId)->where('id_usuario', $userId);
    }
}