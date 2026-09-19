<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Group
 * 
 * @property int $id_grupo
 * @property int $id_carrera
 * @property int $id_materia
 * @property string $num_grupo
 * @property string $gestion
 * @property string $estado
 * @property int|null $id_usuario_docente
 * @property int|null $id_periodo
 */
class Group extends Model
{
    protected $table = 'grupo';
    protected $primaryKey = 'id_grupo';
    public $timestamps = false;

    protected $casts = [
        'id_carrera'         => 'int',
        'id_materia'         => 'int',
        'num_grupo'          => 'string',
        'gestion'            => 'string',
        'estado'             => 'string',
        'id_usuario_docente' => 'int',
        'id_periodo'         => 'int',
    ];

    protected $fillable = [
        'id_carrera',
        'id_materia',
        'num_grupo',
        'gestion',
        'estado',
        'id_usuario_docente',
        'id_periodo',
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'grupo_estudiante', 'id_grupo', 'id_estudiante');
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'grupo_examen', 'id_grupo', 'id_examen');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'id_materia');
    }

    public function tieneNomina(): bool
    {
        return $this->students()->count() > 0;
    }

    public function getCantidadEstudiantesAttribute(): int
    {
        return $this->students()->count();
    }
}
