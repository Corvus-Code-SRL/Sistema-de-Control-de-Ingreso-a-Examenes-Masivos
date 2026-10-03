/**
 * Áreas de trabajo de SCIEM.
 *
 * Cada área tiene su propia navegación y su propio juego de rutas. No se
 * mezclan: una pantalla pertenece a un área, nunca a las dos. El área sale
 * del rol de la sesión (ver `areaForRole`).
 */
export type Area = 'docente' | 'administrador'

export const AREA_LABELS: Record<Area, string> = {
  docente: 'Docente',
  administrador: 'Administrador',
}

/** Nombres de rol tal como los devuelve el backend (`rol.nombre_rol`). */
export type RoleName = 'Administrador' | 'Docente' | 'Auxiliar'

/** Usuario que la aplicación muestra como conectado (avatar y nombre del sidebar). */
export interface CurrentUser {
  nombre: string
  /** Iniciales para el avatar, que es lo único que el diseño muestra. */
  iniciales: string
  area: Area
}

/*
 * ---------------------------------------------------------------------------
 * Formas de la API de autenticación (docs/api/autenticacion.md)
 * ---------------------------------------------------------------------------
 */

export interface SessionUser {
  id_usuario: string
  cod_sis: string
  nombre: string
  apellido_paterno: string
  apellido_materno: string | null
  nombre_completo: string
  correo: string
  estado: string
}

export interface SessionRole {
  id_rol: number
  nombre_rol: string
}

/** POST /api/auth/login */
export interface LoginResponse {
  data: {
    usuario: SessionUser
    rol: SessionRole
    token: string
    tipo_token: 'Bearer'
    /** ISO 8601; `null` si el backend no configura vencimiento. */
    expira_en: string | null
  }
  mensaje?: string
}

/** Permisos del rol vigente y pantallas que habilitan. */
export interface Navigation {
  permisos: string[]
  interfaces: string[]
}

/** GET /api/auth/yo */
export interface MeResponse {
  data: {
    usuario: SessionUser
    rol: SessionRole
    navegacion: Navigation
    password_confirmado_en: string | null
  }
}

/** POST /api/auth/logout */
export interface LogoutResponse {
  data: { sesion_cerrada: boolean }
  mensaje?: string
}

/** POST /api/auth/confirmar-password */
export interface ConfirmPasswordResponse {
  data: { password_confirmado_en: string }
  mensaje?: string
}

/*
 * ---------------------------------------------------------------------------
 * Estado de la sesión en el cliente
 * ---------------------------------------------------------------------------
 */

/**
 * - `verificando`: hay un token guardado y se está comprobando con GET /auth/yo.
 * - `anonimo`: sin sesión; la app funciona igual (usuario fijo del backend).
 * - `autenticado`: sesión vigente.
 * - `expirada`: una petición con token recibió 401; la pantalla se bloquea hasta volver a entrar.
 */
export type AuthStatus = 'verificando' | 'anonimo' | 'autenticado' | 'expirada'

export interface AuthState {
  usuario: SessionUser | null
  rol: SessionRole | null
  token: string | null
  estado: AuthStatus
}

/** Por qué falló un inicio de sesión; la pantalla elige cómo mostrar cada caso. */
export type LoginFailureKind =
  | 'credenciales'
  | 'cuenta-inactiva'
  | 'sin-rol'
  | 'limitado'
  | 'red'
  | 'desconocido'

export interface LoginFailure {
  kind: LoginFailureKind
  message: string
}
