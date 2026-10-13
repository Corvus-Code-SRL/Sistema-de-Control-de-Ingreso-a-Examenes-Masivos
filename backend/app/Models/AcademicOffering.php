<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.oferta_academica: un par materia-carrera que se dicta en un período.
 *
 * La clave es compuesta (carrera, materia, gestion_periodo); grupo la referencia con esa misma clave,
 * así que un grupo solo puede existir para un par ofertado. Se consulta con where(), no con find().
 *
 * @property int $id_carrera
 * @property int $id_materia
 * @property int $id_gestion_periodo
 * @property string $estado
 * @property string $fecha_registro
 */
class AcademicOffering extends Model
{
    protected $table = 'oferta_academica';
    protected $primaryKey = null;

    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id_carrera' => 'int',
        'id_materia' => 'int',
        'id_gestion_periodo' => 'int',
    ];

    protected $fillable = [
        'id_carrera',
        'id_materia',
        'id_gestion_periodo',
        'estado',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'id_gestion_periodo', 'id_gestion_periodo');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id_materia', 'id_materia');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'id_carrera', 'id_carrera');
    }
}
