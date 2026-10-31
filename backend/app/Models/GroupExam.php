<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.grupo_examen.
 *
 * @property int $id_grupo
 * @property int $id_examen
 */
class GroupExam extends Pivot
{

    protected $table = 'grupo_examen';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'id_examen',
    ];

    protected $casts = [
        'id_grupo' => 'int',
        'id_examen' => 'int'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'id_grupo', 'id_grupo');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen', 'id_examen');
    }

    /** Consulta por la FK completa; usar ->get()/->first(), no with(). */
    public function studentExamsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StudentExam::on($this->getConnectionName())
            ->where('id_grupo', $this->id_grupo)
            ->where('id_examen', $this->id_examen);
    }
}
