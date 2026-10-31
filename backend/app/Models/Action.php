<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.accion.
 *
 * @property int $id_accion
 * @property string $operacion
 * @property string $tipo_operacion
 * @property string|null $descripcion
 */
class Action extends Model
{
    protected $table = 'accion';
    protected $primaryKey = 'id_accion';
    public $timestamps = false;

    protected $fillable = [
        'operacion',
        'tipo_operacion',
        'descripcion'
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_accion');
    }
}
