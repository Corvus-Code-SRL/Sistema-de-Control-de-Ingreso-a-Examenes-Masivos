<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            Group::class,
            'grupo_estudiante',
            'id_estudiante',
            'id_grupo'
        )->withPivot([
            'fecha_inscripcion',
            'estado',
        ]);
    }
}