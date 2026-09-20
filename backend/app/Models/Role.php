<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const ADMINISTRADOR = 'Administrador';
    public const DOCENTE       = 'Docente';
    public const AUXILIAR      = 'Auxiliar';

    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = ['nombre_rol', 'descripcion', 'estado'];

    protected $casts = ['fecha_registro' => 'datetime'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_rol', 'id_rol', 'id_usuario')
                    ->withPivot('fecha_inicio', 'fecha_fin');
    }
}
