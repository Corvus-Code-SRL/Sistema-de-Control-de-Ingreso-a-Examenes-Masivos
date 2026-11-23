<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapea public.docente_inactivo_deteccion (HU-062): la primera vez que el sistema detectó inactivo a un docente
 * aprobador sin fecha de cierre de rol. Una fila por cuenta o, si el reporte no tiene aprobador, por registro.
 * No se actualiza.
 *
 * @property int $id_deteccion
 * @property string|null $id_usuario
 * @property int|null $id_cr_estudiante
 * @property Carbon $detectado_en
 */
class InactiveTeacherDetection extends Model
{
    protected $table = 'docente_inactivo_deteccion';
    protected $primaryKey = 'id_deteccion';
    public $timestamps = false;

    protected $casts = [
        'id_usuario' => 'string',
        'id_cr_estudiante' => 'int',
        'detectado_en' => 'datetime',
    ];

    protected $fillable = ['id_usuario', 'id_cr_estudiante', 'detectado_en'];
}
