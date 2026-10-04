<?php

namespace App\Models;

use App\Support\SisCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Mapea public.usuario.
 *
 * @property string $id_usuario
 * @property string $nombre
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $correo
 * @property string $contrasenia
 * @property string $cod_sis
 * @property string $estado
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    public const ESTADO_ACTIVO   = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    protected $keyType = 'string';  // el UUID viaja como string
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'correo',
        'contrasenia',
        'cod_sis',
        'estado'
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

    /** El SIS se guarda siempre en su forma canónica (ver SisCode). */
    public function setCodSisAttribute($value): void
    {
        $this->attributes['cod_sis'] = is_string($value) ? SisCode::normalize($value) : $value;
    }

    /** Permite usar {user} en las rutas resolviendo por id_usuario. */
    public function getRouteKeyName(): string
    {
        return 'id_usuario';
    }

    /* =========================================================================
     * 1. ROL Y PERMISOS (N:M con usuario_rol)
     * ========================================================================= */
    /**
     * usuario_rol tiene PK compuesta (id_usuario, id_rol, fecha_inicio).
     * Eloquent no soporta PK compuestas en un modelo, por eso se trata
     * como tabla pivot mediante belongsToMany.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'usuario_rol', 'id_usuario', 'id_rol')
                    ->using(UserRole::class)
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

    /* =========================================================================
     * 2. DOCENCIA Y GESTIÓN ACADÉMICA (1:N con grupo)
     * ========================================================================= */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'id_usuario_docente', 'id_usuario');
    }

    /* =========================================================================
     * 3. INVITACIONES A EXÁMENES (1:N con invitacion_examen)
     * ========================================================================= */
    // Invitaciones emitidas/creadas por el docente para que otros colaboren.
    public function examInvitations(): HasMany
    {
        return $this->hasMany(ExamInvitation::class, 'id_docente_creador', 'id_usuario');
    }
    // Invitaciones recibidas por el usuario para cuidar/asistir en un examen.
    public function examInvitationsAsReviewer(): HasMany
    {
        return $this->hasMany(ExamInvitation::class, 'id_docente_invitado', 'id_usuario');
    }

    /* =========================================================================
     * 4. REPORTES DE ESTUDIANTES (1:N con reporte_estudiante)
     * ========================================================================= */
    // Reportes de faltas de estudiantes emitidos por este usuario.
    public function studentReports(): HasMany
    {
        return $this->hasMany(StudentReport::class, 'id_usuario_reportante', 'id_usuario');
    }

    // Reportes de faltas de estudiantes asignados a este usuario para su revisión.
    public function studentReportsToReview(): HasMany
    {
        return $this->hasMany(StudentReport::class, 'id_usuario_revisor', 'id_usuario');
    }

    /* =========================================================================
     * 5. REPORTES DE POSTULANTES - ADMISIÓN (1:N con reporte_postulante)
     * ========================================================================= */
    // Reportes de faltas de postulantes emitidos por este usuario.
    public function applicantReports(): HasMany
    {
        return $this->hasMany(ApplicantReport::class, 'id_usuario_reportante', 'id_usuario');
    }
    // Reportes de faltas de postulantes asignados a este usuario para su revisión.
    public function applicantReportsToReview(): HasMany
    {
        return $this->hasMany(ApplicantReport::class, 'id_usuario_revisor', 'id_usuario');
    }

    /* =========================================================================
     * 6. SESIONES Y AUDITORÍA (1:N con sesion y log)
     * =========================================================================*/
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'id_usuario', 'id_usuario');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_usuario', 'id_usuario');
    }

    public function getAuthPassword()
    {
        return $this->contrasenia;
    }

    /** Habilitaciones del usuario como auxiliar de exámenes, con su ambiente (HU-09). */
    public function assistantAssignments(): HasMany
    {
        return $this->hasMany(ExamAssistant::class, 'id_usuario', 'id_usuario');
    }

    /** Historial: identificar una asignación por usuario, rol y fecha_inicio. */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class, 'id_usuario', 'id_usuario');
    }
}
