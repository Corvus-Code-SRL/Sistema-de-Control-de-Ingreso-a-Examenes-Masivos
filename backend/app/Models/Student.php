<?php

namespace App\Models;

use App\Support\SisCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.estudiante.
 *
 * @property int $id_estudiante
 * @property string $cod_sis
 * @property string|null $ci
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
        'estado'
    ];

    /** El SIS se guarda siempre en su forma canónica (ver SisCode). */
    public function setCodSisAttribute($value): void
    {
        $this->attributes['cod_sis'] = is_string($value) ? SisCode::normalize($value) : $value;
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'grupo_estudiante', 'id_estudiante', 'id_grupo')
                    ->using(GroupStudent::class)
                    ->withPivot(['fecha_inscripcion', 'estado']);
    }

    public function studentReports(): HasMany
    {
        return $this->hasMany(StudentReport::class, 'id_estudiante', 'id_estudiante');
    }

    public function riskCenters(): HasMany
    {
        return $this->hasMany(RiskCenter::class, 'id_estudiante', 'id_estudiante');
    }

    public function studentExams(): HasMany
    {
        return $this->hasMany(StudentExam::class, 'id_estudiante', 'id_estudiante');
    }
}
