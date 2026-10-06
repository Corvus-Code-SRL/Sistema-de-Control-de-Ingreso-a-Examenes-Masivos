<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.materia.
 *
 * @property int $id_materia
 * @property string $nombre
 * @property string $codigo
 * @property string|null $descripcion
 * @property string $estado
 */
class Subject extends Model
{
    public const ESTADO_ACTIVO   = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    protected $table = 'materia';
    protected $primaryKey = 'id_materia';
    public $timestamps = false;

    protected $casts = [
        'estado' => 'string'
    ];

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado'
    ];

    /**
     * Relación con las carreras a las que pertenece la materia (materia_carrera).
     */
    public function subjectCareers(): HasMany
    {
        return $this->hasMany(SubjectCareer::class, 'id_materia', 'id_materia');
    }

    /**
     * Filtra únicamente las materias en estado ACTIVO.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereRaw('estado::text = ?', [self::ESTADO_ACTIVO]);
    }

    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'materia_carrera', 'id_materia', 'id_carrera')
            ->using(SubjectCareer::class)
            ->withPivot('nivel_semestre', 'obligatoria', 'estado');
    }
}
