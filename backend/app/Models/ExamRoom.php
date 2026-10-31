<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mapea public.examen_ambiente.
 *
 * @property int $id_examen
 * @property int $id_ambiente
 */
class ExamRoom extends Pivot
{

    protected $table = 'examen_ambiente';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_examen',
        'id_ambiente',
    ];

    protected $casts = [
        'id_examen' => 'int',
        'id_ambiente' => 'int'
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'id_examen');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'id_ambiente');
    }
}
