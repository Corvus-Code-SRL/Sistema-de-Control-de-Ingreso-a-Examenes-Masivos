<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mapea public.grupo.
 *
 * @property int $id_grupo
 * @property int $id_carrera
 * @property int $id_materia
 * @property string $num_grupo
 * @property string $gestion
 * @property string $estado
 * @property string $id_usuario_docente
 * @property int $id_periodo
 */
class Group extends Model
{
    protected $table = 'grupo';
    protected $primaryKey = 'id_grupo';
    public $timestamps = false;

    protected $casts = [
        'id_carrera' => 'int',
        'id_materia' => 'int',
        'estado' => 'string',
        'id_usuario_docente' => 'string',
        'id_periodo' => 'int'
    ];

    protected $fillable = [
        'id_carrera',
        'id_materia',
        'num_grupo',
        'gestion',
        'estado',
        'id_usuario_docente',
        'id_periodo'
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'id_periodo', 'id_periodo');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'grupo_estudiante', 'id_grupo', 'id_estudiante')
                    ->using(GroupStudent::class)
                    ->withPivot('fecha_inscripcion', 'estado');
    }

    /**
     * Incluye el tamaño de la nómina sin lanzar una consulta por grupo.
     *
     * Nómina cargada es tener filas en grupo_estudiante: el estado de la fila no
     * se filtra ni significa nada, la exactitud de la lista es de WEBSIS.
     */
    public function scopeWithStudentCount(Builder $query): Builder
    {
        return $query
            ->select('grupo.*')
            ->selectSub(function ($subquery) {
                $subquery->from('grupo_estudiante')
                    ->selectRaw('count(*)')
                    ->whereColumn('grupo_estudiante.id_grupo', 'grupo.id_grupo');
            }, 'cantidad_estudiantes');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_docente', 'id_usuario');
    }

    /** Consulta por la FK completa; usar ->get()/->first(), no with(). */
    public function subjectCareerQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return SubjectCareer::on($this->getConnectionName())
            ->where('id_carrera', $this->id_carrera)
            ->where('id_materia', $this->id_materia);
    }

    public function examGroups(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'grupo_examen', 'id_grupo', 'id_examen')
                    ->using(GroupExam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_materia', 'id_materia');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'id_carrera', 'id_carrera');
    }

    public function exams(): BelongsToMany
    {
        return $this->examGroups();
    }
}
