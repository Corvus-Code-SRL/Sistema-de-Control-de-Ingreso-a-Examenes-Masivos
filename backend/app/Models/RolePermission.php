<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.permiso_rol: qué permiso tuvo un rol y desde/hasta cuándo (RNF-01). Quitar un permiso
 * cierra la fila con fecha_fin; nunca se borra. La llena RolePermissionSeeder desde config/sciem.php.
 *
 * @property int $id_permiso
 * @property int $id_rol
 * @property string $estado
 * @property \Carbon\Carbon $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 */
class RolePermission extends Pivot
{

    protected $table = 'permiso_rol';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_permiso' => 'int',
        'id_rol' => 'int',
        'estado' => 'string',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    protected $fillable = [
        'id_permiso',
        'id_rol',
        'estado',
        'fecha_inicio',
        'fecha_fin',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'id_permiso', 'id_permiso');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }
}
