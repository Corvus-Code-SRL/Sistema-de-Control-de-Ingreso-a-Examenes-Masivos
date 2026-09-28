<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mapea public.rol.
 *
 * @property int $id_rol
 * @property string $nombre_rol
 * @property string|null $descripcion
 * @property \Carbon\Carbon $fecha_registro
 * @property string $estado
 */
class Role extends Model
{
    public const ADMINISTRADOR = 'Administrador';
    public const DOCENTE       = 'Docente';
    public const AUXILIAR      = 'Auxiliar';

    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $casts = [
        'fecha_registro' => 'datetime',
        'estado' => 'string'
    ];

    protected $fillable = [
        'nombre_rol',
        'descripcion',
        'fecha_registro',
        'estado'
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_rol', 'id_rol', 'id_usuario')
                    ->using(UserRole::class)
                    ->withPivot('fecha_inicio', 'fecha_fin');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permiso_rol', 'id_rol', 'id_permiso')
                    ->using(RolePermission::class)
                    ->withPivot('estado');
    }
}
