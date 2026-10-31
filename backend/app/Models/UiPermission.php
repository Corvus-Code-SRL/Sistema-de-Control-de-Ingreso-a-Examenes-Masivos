<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.ui_permiso.
 *
 * @property int $id_ui
 * @property int $id_permiso
 * @property string $estado
 */
class UiPermission extends Pivot
{

    protected $table = 'ui_permiso';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_ui',
        'id_permiso',
        'estado',
    ];

    protected $casts = [
        'id_ui' => 'int',
        'id_permiso' => 'int',
    ];

    public function ui(): BelongsTo
    {
        return $this->belongsTo(Ui::class, 'id_ui', 'id_ui');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'id_permiso', 'id_permiso');
    }
}
