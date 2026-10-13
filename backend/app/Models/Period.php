<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapea public.gestion_periodo: la instancia de un período en una gestión («1/2026»).
 *
 * Es lo que la API llama «período» (su id viaja como `id_periodo`). `estado` dice si el registro
 * está habilitado; `vigente` marca el único período en curso (un índice parcial lo garantiza).
 *
 * @property int $id_gestion_periodo
 * @property int $id_gestion
 * @property int $id_periodo
 * @property string|null $fecha_inicio
 * @property string|null $fecha_fin
 * @property bool $vigente
 * @property string $estado
 * @property-read int $year Año de la gestión.
 * @property-read string $label Etiqueta estándar «{codigo}/{anio}»: «0/2026», «1/2026», «3/2026»…
 * @property-read string $type_name Nombre del catálogo: «Semestre 1», «Anual», «Verano»…
 */
class Period extends Model
{
    protected $table = 'gestion_periodo';
    protected $primaryKey = 'id_gestion_periodo';
    public $timestamps = false;

    protected $casts = [
        'id_gestion' => 'int',
        'id_periodo' => 'int',
        'vigente' => 'boolean',
    ];

    protected $fillable = [
        'id_gestion',
        'id_periodo',
        'fecha_inicio',
        'fecha_fin',
        'vigente',
        'estado',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'id_gestion', 'id_gestion');
    }

    public function periodType(): BelongsTo
    {
        return $this->belongsTo(PeriodType::class, 'id_periodo', 'id_periodo');
    }

    public function admissionCalls(): HasMany
    {
        return $this->hasMany(AdmissionCall::class, 'id_gestion_periodo', 'id_gestion_periodo');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'id_gestion_periodo', 'id_gestion_periodo');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(AcademicOffering::class, 'id_gestion_periodo', 'id_gestion_periodo');
    }

    /** El único período vigente (en curso). */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('gestion_periodo.vigente', true);
    }

    /**
     * Trae el año y el tipo de período en la misma consulta (dos JOIN), para poder mostrar la
     * etiqueta de muchos períodos o de los de muchos grupos sin consultas adicionales.
     */
    public function scopeWithLabel(Builder $query): Builder
    {
        return $query
            ->join('gestion', 'gestion.id_gestion', '=', 'gestion_periodo.id_gestion')
            ->join('periodo', 'periodo.id_periodo', '=', 'gestion_periodo.id_periodo')
            ->addSelect([
                'gestion_periodo.*',
                'gestion.anio as anio',
                'periodo.codigo as codigo_periodo',
                'periodo.nombre as nombre_tipo_periodo',
            ]);
    }

    /** El año de la gestión (2026). */
    public function getYearAttribute(): int
    {
        if (array_key_exists('anio', $this->attributes)) {
            return (int) $this->attributes['anio'];
        }

        return (int) $this->academicYear->anio;
    }

    /** Nombre del tipo de período en el catálogo («Semestre 1», «Anual», «Verano»…). */
    public function getTypeNameAttribute(): string
    {
        if (array_key_exists('nombre_tipo_periodo', $this->attributes)) {
            return (string) $this->attributes['nombre_tipo_periodo'];
        }

        return (string) $this->periodType->nombre;
    }

    /** Etiqueta estándar de la UMSS: «{codigo}/{anio}», para todos los períodos («0/2026», «3/2026»…). */
    public function getLabelAttribute(): string
    {
        if (array_key_exists('codigo_periodo', $this->attributes)) {
            $code = (int) $this->attributes['codigo_periodo'];
        } else {
            $code = (int) $this->periodType->codigo;
        }

        return sprintf('%d/%d', $code, $this->year);
    }
}
