<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;

    public const ESTADO_ACTIVO   = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';

    public $incrementing = false;   // la PK no es un entero autoincremental
    protected $keyType = 'string';  // el UUID viaja como string
    public $timestamps = false;     // la tabla no tiene created_at/updated_at

    protected $fillable = [
        'id_usuario',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'correo',
        'contrasenia',
        'cod_sis',
        'estado',
    ];

    /** Nunca se serializa la contraseña, pase lo que pase. */
    protected $hidden = ['contrasenia'];

    /**
     * Genera el UUID desde PHP antes de insertar.
     *
     * Si se dejara el DEFAULT gen_random_uuid() de Postgres, Eloquent no
     * sabría qué id se generó y $user->id_usuario quedaría vacío después
     * del create(), rompiendo la bitácora y la respuesta al frontend.
     */
    protected static function booted()
    {
        static::creating(function (self $user) {
            if (empty($user->id_usuario)) {
                $user->id_usuario = (string) Str::uuid();
            }
        });
    }

    /** Permite usar {user} en las rutas resolviendo por id_usuario. */
    public function getRouteKeyName(): string
    {
        return 'id_usuario';
    }

    /**
     * usuario_rol tiene PK compuesta (id_usuario, id_rol, fecha_inicio).
     * Eloquent no soporta PK compuestas en un modelo, por eso se trata
     * como tabla pivot mediante belongsToMany.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'usuario_rol', 'id_usuario', 'id_rol')
                    ->withPivot('fecha_inicio', 'fecha_fin');
    }

    /**
     * Rol vigente como relación: el único que todavía no fue cerrado.
     *
     * Se declara como relación para poder cargarlo con with() al listar cuentas.
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->wherePivotNull('fecha_fin');
    }

    /** Si activeRoles ya viene cargada se reutiliza, sin volver a consultar. */
    public function rolActivo(): ?Role
    {
        if ($this->relationLoaded('activeRoles')) {
            return $this->activeRoles->first();
        }

        return $this->activeRoles()->first();
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereRaw('estado::text = ?', [self::ESTADO_ACTIVO]);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }
}