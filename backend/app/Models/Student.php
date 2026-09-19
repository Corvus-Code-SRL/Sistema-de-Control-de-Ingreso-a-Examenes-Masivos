<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Student
 * 
 * @property int $id_estudiante
 * @property string $cod_sis
 * @property string $ci
 * @property string $nombre
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string|null $correo_institucional
 * @property string|null $telefono
 * @property string $estado
 */
class Student extends Model
{
    protected $table = 'estudiante';
    protected $primaryKey = 'id_estudiante';
    public $timestamps = false;

    protected $fillable = [
        'cod_sis',
        'ci',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'correo_institucional',
        'telefono',
        'estado',
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'grupo_estudiante', 'id_estudiante', 'id_grupo');
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'examen_estudiante', 'id_estudiante', 'id_examen')
                    ->withPivot('estado_habilitacion', 'observacion', 'estado_ingreso', 'hora_ingreso', 'id_grupo');
    }
}
