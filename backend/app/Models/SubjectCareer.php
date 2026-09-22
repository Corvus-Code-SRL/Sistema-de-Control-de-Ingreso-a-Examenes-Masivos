<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Par materia-carrera: la unidad real de trabajo del docente.
 *
 * Una misma materia puede pertenecer a varias carreras, por eso el contexto
 * académico nunca se resuelve por materia sola.
 */
class SubjectCareer extends Model
{
    protected $table = 'materia_carrera';

    /*
     * La tabla tiene clave primaria compuesta (id_carrera, id_materia), que Eloquent
     * no soporta de forma nativa: el modelo se usa para lectura y para inserciones
     * explícitas, nunca para operaciones basadas en una clave única.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_carrera',
        'id_materia',
        'nivel_semestre',
        'obligatoria',
        'estado',
    ];

    protected $casts = [
        'obligatoria' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_materia', 'id_materia');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'id_carrera', 'id_carrera');
    }
}
