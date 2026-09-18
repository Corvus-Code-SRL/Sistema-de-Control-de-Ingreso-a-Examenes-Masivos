<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'materia';

    protected $primaryKey = 'id_materia';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado',
    ];

    public function subjectCareers(): HasMany
    {
        return $this->hasMany(SubjectCareer::class, 'id_materia', 'id_materia');
    }
}
