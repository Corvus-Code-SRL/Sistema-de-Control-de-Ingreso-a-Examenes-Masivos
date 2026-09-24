<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $table = 'carrera';

    protected $primaryKey = 'id_carrera';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado',
        'id_facultad',
    ];

    public function subjectCareers(): HasMany
    {
        return $this->hasMany(SubjectCareer::class, 'id_carrera', 'id_carrera');
    }
}
