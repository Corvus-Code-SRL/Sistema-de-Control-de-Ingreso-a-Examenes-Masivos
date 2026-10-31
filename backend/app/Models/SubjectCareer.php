<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.materia_carrera.
 *
 * @property int $id_carrera
 * @property int $id_materia
 * @property string|null $nivel_semestre
 * @property bool|null $obligatoria
 * @property string $estado
 */
class SubjectCareer extends Pivot
{

    protected $table = 'materia_carrera';
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
        'id_carrera' => 'integer',
        'id_materia' => 'integer',
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

    /** Consulta por la FK completa; usar ->get()/->first(), no with(). */
    public function groupsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Group::on($this->getConnectionName())
            ->where('id_carrera', $this->id_carrera)
            ->where('id_materia', $this->id_materia);
    }
}
