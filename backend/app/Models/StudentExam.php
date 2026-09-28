<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.examen_estudiante.
 *
 * @property int $id_examen
 * @property int $id_estudiante
 * @property string $estado_habilitacion
 * @property string|null $observacion
 * @property string $estado_ingreso
 * @property string|null $hora_ingreso
 * @property int $id_grupo
 */
class StudentExam extends Pivot
{
    protected $table = 'examen_estudiante';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_estudiante' => 'int',
        'estado_habilitacion' => 'string',
        'estado_ingreso' => 'string',
        'hora_ingreso' => 'string',
        'id_grupo' => 'int'
    ];

    protected $fillable = [
        'id_examen',
        'id_estudiante',
        'estado_habilitacion',
        'observacion',
        'estado_ingreso',
        'hora_ingreso',
        'id_grupo',
    ];

    /** Consulta por la FK completa; usar ->get()/->first(), no with(). */
    public function groupExamQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return GroupExam::on($this->getConnectionName())
            ->where('id_grupo', $this->id_grupo)
            ->where('id_examen', $this->id_examen);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    /** Consulta por la FK completa; usar ->get()/->first(), no with(). */
    public function groupStudentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return GroupStudent::on($this->getConnectionName())
            ->where('id_grupo', $this->id_grupo)
            ->where('id_estudiante', $this->id_estudiante);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }
}
