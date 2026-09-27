<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.tipo_examen.
 *
 * @property int $id_tipo_examen
 * @property string $nombre
 * @property string $categoria
 */
class ExamType extends Model
{
    protected $table = 'tipo_examen';
    protected $primaryKey = 'id_tipo_examen';
    public $timestamps = false;

    protected $casts = [
        'categoria' => 'string'
    ];

    protected $fillable = [
        'nombre',
        'categoria'
    ];

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'id_tipo_examen', 'id_tipo_examen');
    }
}
