<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
/**
 * Mapea public.postulante.
 *
 * @property int $id_postulante
 * @property string $ci
 * @property string $nombres
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string|null $correo
 * @property string|null $telefono
 * @property string $estado
 */
class Applicant extends Model
{
    protected $table = 'postulante';
    protected $primaryKey = 'id_postulante';
    public $timestamps = false;

    protected $fillable = [
        'ci',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'correo',
        'telefono',
        'estado'
    ];

    public function applicantExams(): HasMany
    {
        return $this->hasMany(ApplicantExam::class, 'id_postulante', 'id_postulante');
    }

    public function applicantReports(): HasMany
    {
        return $this->hasMany(ApplicantReport::class, 'id_postulante', 'id_postulante');
    }

    public function applicantRiskCenters(): HasMany
    {
        return $this->hasMany(ApplicantRiskCenter::class, 'id_postulante', 'id_postulante');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'examen_postulante', 'id_postulante', 'id_examen')
                    ->using(ApplicantExam::class)
                    ->withPivot(['estado_habilitacion', 'estado_ingreso', 'hora_ingreso']);
    }
}
