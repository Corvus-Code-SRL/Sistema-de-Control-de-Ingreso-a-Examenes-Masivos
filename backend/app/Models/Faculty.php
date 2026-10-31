<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.facultad.
 *
 * @property int $id_facultad
 * @property string $nombre
 * @property string $codigo
 * @property string|null $descripcion
 * @property string $estado
 */
class Faculty extends Model
{
    protected $table = 'facultad';
    protected $primaryKey = 'id_facultad';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado'
    ];

    public function careers(): HasMany
    {
        return $this->hasMany(Career::class, 'id_facultad', 'id_facultad');
    }

    public function admissionCalls(): HasMany
    {
        return $this->hasMany(AdmissionCall::class, 'id_facultad', 'id_facultad');
    }
}
