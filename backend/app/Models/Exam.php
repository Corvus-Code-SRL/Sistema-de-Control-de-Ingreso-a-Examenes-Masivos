<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Examen de un par materia-carrera, creado por un docente.
 *
 * El par se guarda completo (id_carrera, id_materia) con clave foránea compuesta
 * hacia materia_carrera, igual que grupo: la materia sola es ambigua.
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
        'estado',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'duracion'       => 'integer',
        'id_tipo_examen' => 'integer',
        'id_carrera'     => 'integer',
        'id_materia'     => 'integer',
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
        return $this->belongsToMany(Classroom::class, 'examen_ambiente', 'id_examen', 'id_ambiente');
    }
}
