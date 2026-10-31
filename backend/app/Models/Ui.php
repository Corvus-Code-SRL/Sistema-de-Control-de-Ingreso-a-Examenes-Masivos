<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mapea public.ui.
 *
 * @property int $id_ui
 * @property string $nombre_ui
 * @property string|null $descripcion
 * @property string $estado
 */
class Ui extends Model
{
    protected $table = 'ui';
    protected $primaryKey = 'id_ui';
    public $timestamps = false;

    protected $fillable = [
        'nombre_ui',
        'descripcion',
        'estado'
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'ui_permiso', 'id_ui', 'id_permiso')
                    ->using(UiPermission::class)
                    ->withPivot('estado');
    }
}
