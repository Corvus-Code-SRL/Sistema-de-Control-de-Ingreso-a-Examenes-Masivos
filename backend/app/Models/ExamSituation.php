<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/** Registra un cambio de ambiente o una observación de identidad */
class ExamSituation extends Model
{
    protected $table = 'situacion_examen';
    protected $primaryKey = 'id_situacion';
    public $timestamps = false;

    protected $fillable = [
        'id_examen', 'id_estudiante', 'tipo', 'id_ambiente_origen', 
        'id_ambiente_destino', 'motivo', 'registrado_en', 'id_usuario_registrante',
    ];

    protected $casts = [
        'id_examen' => 'integer',
        'id_estudiante' => 'integer',
        'id_ambiente_origen' => 'integer',
        'id_ambiente_destino' => 'integer',
        'registrado_en' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function originRoom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente_origen', 'id_ambiente');
    }

    public function destinationRoom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente_destino', 'id_ambiente');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_registrante', 'id_usuario');
    }
}
