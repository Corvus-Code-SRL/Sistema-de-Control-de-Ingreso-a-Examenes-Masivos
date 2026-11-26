<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Planificación por examen y estudiante (HU-031).
 * La clave es compuesta: las escrituras masivas se delimitan explícitamente en el Service.
 */
class RoomDistribution extends Model
{
    protected $table = 'distribucion_ambiente';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_examen', 'id_estudiante', 'id_ambiente', 'id_usuario_asignador', 'asignado_en',
    ];

    protected $casts = [
        'id_examen' => 'integer',
        'id_estudiante' => 'integer',
        'id_ambiente' => 'integer',
        'id_usuario_asignador' => 'string',
        'asignado_en' => 'string',
    ];
}
