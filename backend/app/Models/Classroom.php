<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ambiente (aula) del catálogo general donde se rinden los exámenes.
 */
class Classroom extends Model
{
    protected $table = 'ambiente';

    protected $primaryKey = 'id_ambiente';

    public $timestamps = false;

    protected $fillable = [
        'nro_aula',
        'capacidad',
        'ubicacion',
        'estado',
    ];

    protected $casts = [
        'capacidad' => 'integer',
    ];

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'examen_ambiente', 'id_ambiente', 'id_examen');
    }
}
