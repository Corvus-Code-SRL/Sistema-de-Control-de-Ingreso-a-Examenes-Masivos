<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.solicitud_ingreso_retraso: la solicitud de un auxiliar para registrar con
 * retraso a un estudiante de un examen EN_CURSO (HU-037), y su resolución por el docente.
 *
 * @property int $id_solicitud
 * @property int $id_examen
 * @property int $id_estudiante
 * @property int $id_ambiente
 * @property string $id_usuario_solicitante
 * @property string|null $id_usuario_resolutor
 * @property string $estado
 * @property \Carbon\Carbon $solicitado_en
 * @property \Carbon\Carbon|null $resuelto_en
 */
class LateEntryRequest extends Model
{
    /** Valores del CHECK chk_solicitud_retraso_estado. */
    public const PENDIENTE = 'PENDIENTE';
    public const AUTORIZADA = 'AUTORIZADA';
    public const RECHAZADA = 'RECHAZADA';

    protected $table = 'solicitud_ingreso_retraso';
    protected $primaryKey = 'id_solicitud';
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_estudiante' => 'int',
        'id_ambiente' => 'int',
        'id_usuario_solicitante' => 'string',
        'id_usuario_resolutor' => 'string',
        'solicitado_en' => 'datetime',
        'resuelto_en' => 'datetime',
    ];

    protected $fillable = [
        'id_examen',
        'id_estudiante',
        'id_ambiente',
        'id_usuario_solicitante',
        'id_usuario_resolutor',
        'estado',
        'solicitado_en',
        'resuelto_en',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_estudiante', 'id_estudiante');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente', 'id_ambiente');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_solicitante', 'id_usuario');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_resolutor', 'id_usuario');
    }
}
