<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mapea public.permiso.
 *
 * @property int $id_permiso
 * @property string $nombre_permiso
 * @property string|null $descripcion
 * @property string $estado
 */
class Permission extends Model
{
    protected $table = 'permiso';
    protected $primaryKey = 'id_permiso';
    public $timestamps = false;

    protected $fillable = [
        'nombre_permiso',
        'descripcion',
        'estado'
    ];

    public function uis(): BelongsToMany
    {
        return $this->belongsToMany(Ui::class, 'ui_permiso', 'id_permiso', 'id_ui')
                    ->using(UiPermission::class)
                    ->withPivot('estado');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permiso_rol', 'id_permiso', 'id_rol')
                    ->using(RolePermission::class)
                    ->withPivot('estado');
    }
}
