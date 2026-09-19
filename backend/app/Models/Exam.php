<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Exam
 * 
 * @property int $id_examen
 * @property string $nombre_examen
 * @property \Carbon\Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property int $duracion
 * @property string $normas
 * @property int $id_tipo_examen
 * 
 * @property ExamType $examType
 * @property Collection|ExamenAdmision[] $admissionExams
 * @property Collection|Classroom[] $classrooms
 * @property Collection|Postulante[] $applicants
 * @property Collection|InvitacionExamen[] $invitationExams
 * @property Collection|ReporteEstudiante[] $studentReports
 * @property Collection|ReportePostulante[] $applicantReports
 * @property Collection|Group[] $groups
 * @property Collection|Student[] $students
 * 
 * @package App\Models
 */
class Exam extends Model
{
	protected $table = 'examen';
	protected $primaryKey = 'id_examen';
	public $timestamps = false;

	protected $casts = [
		'nombre_examen'  => 'string',
		'fecha'          => 'date',
		'hora_inicio'    => 'string',
		'hora_fin'       => 'string',
		'duracion'       => 'int',
		'normas'         => 'string',
		'id_tipo_examen' => 'int',
	];

	protected $fillable = [
		'nombre_examen',
		'fecha',
		'hora_inicio',
		'hora_fin',
		'duracion',
		'normas',
		'id_tipo_examen',
	];

	public function examType()
	{
		return $this->belongsTo(ExamType::class, 'id_tipo_examen');
	}

	public function tipo_examen()
	{
		return $this->examType();
	}

	public function admissionExams()
	{
		return $this->hasMany(ExamenAdmision::class, 'id_examen');
	}

	public function classrooms()
	{
		return $this->belongsToMany(Classroom::class, 'examen_ambiente', 'id_examen', 'id_ambiente');
	}

	public function ambiente()
	{
		return $this->classrooms();
	}

	public function applicants()
	{
		return $this->belongsToMany(Postulante::class, 'examen_postulante', 'id_examen', 'id_postulante')
					->withPivot('estado_habilitacion', 'estado_ingreso', 'hora_ingreso');
	}

	public function invitationExams()
	{
		return $this->hasMany(InvitacionExamen::class, 'id_examen');
	}

	public function studentReports()
	{
		return $this->hasMany(ReporteEstudiante::class, 'id_examen');
	}

	public function applicantReports()
	{
		return $this->hasMany(ReportePostulante::class, 'id_examen');
	}

	public function groups()
	{
		return $this->belongsToMany(Group::class, 'grupo_examen', 'id_examen', 'id_grupo');
	}

	public function students()
	{
		return $this->belongsToMany(Student::class, 'examen_estudiante', 'id_examen', 'id_estudiante')
					->withPivot('estado_habilitacion', 'observacion', 'estado_ingreso', 'hora_ingreso', 'id_grupo');
	}
}
