<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.grupo_estudiante.
 *
 * @property int $id_grupo
 * @property int $id_estudiante
 * @property \Carbon\Carbon $fecha_inscripcion
 * @property string $estado
 */
class GroupStudent extends Pivot
{

    protected $table = 'grupo_estudiante';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_grupo' => 'int',
        'id_estudiante' => 'int',
        'fecha_inscripcion' => 'date',
    ];

    protected $fillable = [
        'id_grupo',
        'id_estudiante',
        'fecha_inscripcion',
        'estado',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'id_grupo', 'id_grupo');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    /** Consulta por la FK completa; usar ->get()/->first(), no with().  o en estos casos utilizar funciones de sql puro*/
    public function studentExamsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StudentExam::on($this->getConnectionName())
            ->where('id_grupo', $this->id_grupo)
            ->where('id_estudiante', $this->id_estudiante);
    }

}
