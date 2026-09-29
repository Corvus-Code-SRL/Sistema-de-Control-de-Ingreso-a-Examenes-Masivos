<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.invitacion_examen.
 *
 * @property int $id_invitacion
 * @property int $id_examen
 * @property string $id_docente_creador
 * @property string $id_docente_invitado
 * @property \Carbon\Carbon $fecha_invitacion
 * @property \Carbon\Carbon|null $fecha_respuesta
 * @property string $estado
 */
class ExamInvitation extends Model
{
    protected $table = 'invitacion_examen';
    protected $primaryKey = 'id_invitacion';
    public $timestamps = false;

    protected $casts = [
        'id_examen' => 'int',
        'id_docente_creador' => 'string',
        'id_docente_invitado' => 'string',
        'fecha_invitacion' => 'datetime',
        'fecha_respuesta' => 'datetime',
    ];

    protected $fillable = [
        'id_examen',
        'id_docente_creador',
        'id_docente_invitado',
        'fecha_invitacion',
        'fecha_respuesta',
        'estado'
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    public function userInvited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente_invitado', 'id_usuario');
    }

    public function userCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_docente_creador', 'id_usuario');
    }
}
