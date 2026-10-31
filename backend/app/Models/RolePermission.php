<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.permiso_rol.
 *
 * @property int $id_permiso
 * @property int $id_rol
 * @property string $estado
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
        'estado' => 'string'
    ];

    protected $fillable = [
        'id_permiso',
        'id_rol',
        'estado',
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
