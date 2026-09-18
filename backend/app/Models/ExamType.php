<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamType
 * 
 * @property int $id_tipo_examen
 * @property string $nombre
 * @property string $categoria
 * 
 * @property Collection|Exam[] $exams
 * 
 * @package App\Models
 */
class ExamType extends Model
{
	protected $table = 'tipo_examen';
	protected $primaryKey = 'id_tipo_examen';
	public $timestamps = false;

	protected $casts = [
		'nombre'    => 'string',
		'categoria' => 'string'
	];

	protected $fillable = [
		'nombre',
		'categoria'
	];

	public function exams()
	{
		return $this->hasMany(Exam::class, 'id_tipo_examen');
	}
}
