<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de examen. Cada tipo pertenece a una categoría de public.categoria_examen.
 */
class ExamType extends Model
{
    protected $table = 'tipo_examen';

    protected $primaryKey = 'id_tipo_examen';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'categoria',
    ];

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'id_tipo_examen', 'id_tipo_examen');
    }
}
