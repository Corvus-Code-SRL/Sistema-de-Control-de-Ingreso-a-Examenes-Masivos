<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grupo de una materia dentro de una carrera.
 *
 * Es la única vía por la que un docente queda vinculado a un par materia-carrera:
 * no existe relación directa entre docente y materia.
 */
class Group extends Model
{
    protected $table = 'grupo';

    protected $primaryKey = 'id_grupo';

    public $timestamps = false;

    protected $fillable = [
        'id_carrera',
        'id_materia',
        'num_grupo',
        'gestion',
        'estado',
        'id_usuario_docente',
        'id_periodo',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'id_periodo', 'id_periodo');
    }
}
