<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mapea public.ambiente.
 *
 * @property int $id_ambiente
 * @property string $nro_aula
 * @property int $capacidad
 * @property string|null $ubicacion
 * @property string $estado
 */
class Classroom extends Model
{
    protected $table = 'ambiente';
    protected $primaryKey = 'id_ambiente';
    public $timestamps = false;

    protected $casts = [
        'capacidad' => 'integer',
        'estado' => 'string'
    ];

    protected $fillable = [
        'nro_aula',
        'capacidad',
        'ubicacion',
        'estado'
    ];

    public function exams(): BelongsToMany
    {
        //la tabla examen_ambiente solo es pivote por lo tanto se hace una conexion directa
        //parametros para este tipo de relaciones:
        //examen_ambiente tabla pivote
        //id_ambiente clave foránea del modelo actual en la tabla pivote
        //id_examen clave foránea del modelo destino en la tabla pivote
        return $this->belongsToMany(Exam::class,'examen_ambiente','id_ambiente','id_examen')
                    ->using(ExamRoom::class);
    }
}
