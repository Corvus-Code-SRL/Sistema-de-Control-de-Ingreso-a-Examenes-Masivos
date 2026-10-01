<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Mapea public.examen.
 *
 * @property int $id_examen
 * @property string $nombre_examen
 * @property \Carbon\Carbon $fecha
 * @property string $hora_inicio
 * @property string|null $hora_fin
 * @property int|null $duracion
 * @property string|null $normas
 * @property int $id_tipo_examen
 * @property int|null $id_carrera
 * @property int|null $id_materia
 * @property string $id_usuario_docente
 * @property string $estado
 */
class Exam extends Model
{
    /** Valores de public.estado_examen. */
    public const PROGRAMADO = 'PROGRAMADO';
    public const EN_INGRESO = 'EN_INGRESO';
    public const EN_CURSO   = 'EN_CURSO';
    public const FINALIZADO = 'FINALIZADO';
    public const CANCELADO  = 'CANCELADO';

    protected $table = 'examen';
    protected $primaryKey = 'id_examen';
    public $timestamps = false;

    protected $casts = [
        'fecha' => 'date',
        'duracion' => 'integer',
        'minutos_apertura' => 'integer',
        'id_tipo_examen' => 'integer',
        'id_carrera' => 'integer',
        'id_materia' => 'integer',
        'id_usuario_docente' => 'string'
    ];

    protected $fillable = [
        'nombre_examen',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'duracion',
        'normas',
        'id_tipo_examen',
        'id_carrera',
        'id_materia',
        'id_usuario_docente',
        'estado'
    ];

    
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'id_tipo_examen', 'id_tipo_examen');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_materia', 'id_materia');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'id_carrera', 'id_carrera');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_docente', 'id_usuario');
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'examen_ambiente', 'id_examen', 'id_ambiente')
            ->using(ExamRoom::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'grupo_examen', 'id_examen', 'id_grupo')
            ->using(GroupExam::class);
    }

    public function admissionExam(): HasOne
    {
        return $this->hasOne(AdmissionExam::class, 'id_examen', 'id_examen');
    }

	public function examInvitations(): HasMany
	{
		return $this->hasMany(ExamInvitation::class, 'id_examen', 'id_examen');
	}

	public function studentsReports(): HasMany
	{
		return $this->hasMany(StudentReport::class, 'id_examen', 'id_examen');
	}

	/* =========================================================================
     * ASISTENCIA / HABILITACIÓN DE EVALUADOS (N:M)
     * ========================================================================= */

    public function applicantReports(): HasMany
    {
        return $this->hasMany(ApplicantReport::class, 'id_examen', 'id_examen');
    }

    public function applicants(): BelongsToMany
    {
        return $this->belongsToMany(Applicant::class, 'examen_postulante', 'id_examen', 'id_postulante')
                    ->using(ApplicantExam::class)
                    ->withPivot('estado_habilitacion', 'estado_ingreso', 'hora_ingreso');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'examen_estudiante', 'id_examen', 'id_estudiante')
                    ->using(StudentExam::class)
                    ->withPivot('id_grupo', 'estado_habilitacion', 'estado_ingreso', 'hora_ingreso', 'observacion');
    }
}
