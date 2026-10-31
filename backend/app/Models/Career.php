<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.carrera.
 *
 * @property int $id_carrera
 * @property string $nombre
 * @property string $codigo
 * @property string|null $descripcion
 * @property string $estado
 * @property int $id_facultad
 */
class Career extends Model
{
    protected $table = 'carrera';
    protected $primaryKey = 'id_carrera';
    public $timestamps = false;

    protected $casts = [
        'estado' => 'string',
        'id_facultad' => 'int'
    ];

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado',
        'id_facultad'
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'id_facultad','id_facultad');
    }

    public function subjectCareers(): HasMany
    {
        return $this->hasMany(SubjectCareer::class, 'id_carrera','id_carrera');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'materia_carrera', 'id_carrera', 'id_materia')
            ->using(SubjectCareer::class)
            ->withPivot('nivel_semestre', 'obligatoria', 'estado');
    }
}
