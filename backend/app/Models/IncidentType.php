<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.tipo_falta.
 *
 * @property int $id_falta
 * @property string $nombre
 * @property string|null $descripcion
 */
class IncidentType extends Model
{
    protected $table = 'tipo_falta';
    protected $primaryKey = 'id_falta';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    public function studentReports(): HasMany
    {
        return $this->hasMany(StudentReport::class, 'id_tipo_falta', 'id_falta');
    }

    public function applicantReports(): HasMany
    {
        return $this->hasMany(ApplicantReport::class, 'id_tipo_falta', 'id_falta');
    }
}
