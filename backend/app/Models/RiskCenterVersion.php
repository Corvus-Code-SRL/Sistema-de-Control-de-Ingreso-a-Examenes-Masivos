<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.central_riesgo_version: una versión del tipo de falta y la descripción de un registro.
 *
 * Solo una versión por registro es la vigente (índice único parcial). Una versión guardada no se edita
 * ni se borra: salvo la bandera `es_vigente`, que pasa a falso al rectificar.
 *
 * @property int $id_version
 * @property int $id_cr_estudiante
 * @property int $numero_version
 * @property int $id_tipo_falta
 * @property string|null $descripcion
 * @property string|null $motivo nulo en la versión 1: es el valor original, sin rectificación
 * @property string $id_usuario_autor
 * @property \Carbon\Carbon $fecha_registro
 * @property bool $es_vigente
 */
class RiskCenterVersion extends Model
{
    protected $table = 'central_riesgo_version';
    protected $primaryKey = 'id_version';
    public $timestamps = false;

    protected $casts = [
        'id_cr_estudiante' => 'int',
        'numero_version' => 'int',
        'id_tipo_falta' => 'int',
        'id_usuario_autor' => 'string',
        'fecha_registro' => 'datetime',
        'es_vigente' => 'boolean',
    ];

    protected $fillable = [
        'id_cr_estudiante',
        'numero_version',
        'id_tipo_falta',
        'descripcion',
        'motivo',
        'id_usuario_autor',
        'fecha_registro',
        'es_vigente',
    ];

    public function riskCenter(): BelongsTo
    {
        return $this->belongsTo(RiskCenter::class, 'id_cr_estudiante', 'id_cr_estudiante');
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class, 'id_tipo_falta', 'id_falta');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_autor', 'id_usuario');
    }
}
