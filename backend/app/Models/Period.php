<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.periodo.
 *
 * @property int $id_periodo
 * @property string $nombre_periodo
 * @property int $gestion
 */
class Period extends Model
{
    protected $table = 'periodo';
    protected $primaryKey = 'id_periodo';
    public $timestamps = false;

    protected $fillable = [
        'nombre_periodo',
        'gestion'
    ];

    public function admissionCalls(): HasMany
    {
        return $this->hasMany(AdmissionCall::class, 'id_periodo', 'id_periodo');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'id_periodo', 'id_periodo');
    }
}
