<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Classroom;

/**
 * Mapea public.intento_ingreso: un ingreso rechazado en el control de entrada de un examen.
 *
 * `id_estudiante` es nulo cuando el controlador no encontró al estudiante (motivo NO_ENCONTRADO).
 * Un intento origina como máximo un reporte (reporte_estudiante.id_intento es UNIQUE).
 *
 * @property int $id_intento
 * @property int $id_examen
 * @property int|null $id_estudiante
 * @property string|null $cod_sis
 * @property string|null $ci_presentado
 * @property int $id_ambiente
 * @property string $id_usuario_controlador
 * @property string $motivo
 * @property string|null $observacion
 * @property \Carbon\Carbon $registrado_en
 */
class EntryAttempt extends Model
{
    protected $table = 'intento_ingreso';
    protected $primaryKey = 'id_intento';
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_estudiante' => 'int',
        'id_ambiente' => 'int',
        'id_usuario_controlador' => 'string',
        'registrado_en' => 'datetime'
    ];

    protected $fillable = [
        'id_examen',
        'id_estudiante',
        'cod_sis',
        'ci_presentado',
        'id_ambiente',
        'id_usuario_controlador',
        'motivo',
        'observacion',
        'registrado_en'
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente', 'id_ambiente');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_controlador', 'id_usuario');
    }

    public function studentReport(): HasOne
    {
        return $this->hasOne(StudentReport::class, 'id_intento', 'id_intento');
    }
}
