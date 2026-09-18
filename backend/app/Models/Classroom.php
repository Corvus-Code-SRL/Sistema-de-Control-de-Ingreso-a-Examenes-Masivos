<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Classroom
 * 
 * @property int $id_ambiente
 * @property string $nro_aula
 * @property int $capacidad
 * @property string|null $ubicacion
 * @property string $estado
 * 
 * @property Collection|Exam[] $exams
 *
 * @package App\Models
 */
class Classroom extends Model
{
	protected $table = 'ambiente';
	protected $primaryKey = 'id_ambiente';
	public $timestamps = false;

	protected $casts = [
		'capacidad' => 'int',
		'estado' => 'string'
	];

	protected $fillable = [
		'nro_aula',
		'capacidad',
		'ubicacion',
		'estado'
	];

	public function exams()
	{
		return $this->belongsToMany(Exam::class, 'examen_ambiente', 'id_ambiente', 'id_examen');
	}
}
