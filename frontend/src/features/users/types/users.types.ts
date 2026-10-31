import { GUEST_ROLE_NAME } from '@/lib/roleLabel'

/**
 * Tipos del feature users, tal como los devuelve el módulo Security.
 */

export type AccountStatus = 'ACTIVO' | 'INACTIVO'

/** Rol del catálogo de SCIEM. */
export interface Role {
  id_rol: number
  nombre_rol: string
  descripcion: string | null
  estado: AccountStatus
}

/** Rol vigente dentro de una cuenta: solo lo necesario para mostrarlo. */
export interface AccountRole {
  id_rol: number
  nombre_rol: string
}

export interface UserAccount {
  id_usuario: string
  cod_sis: string
  nombre: string
  apellido_paterno: string
  apellido_materno: string | null
  nombre_completo: string
  correo: string
  estado: AccountStatus
  /** Docente invitado: su código lo generó el sistema (INV + 4 dígitos) y no es un código SIS. */
  es_invitado: boolean
  /** `null` mientras la cuenta no tenga rol asignado. */
  rol: AccountRole | null
}

/** Un tramo del historial; `fecha_fin` nula indica el rol vigente. */
export interface RoleHistoryEntry {
  id_rol: number
  nombre_rol: string
  fecha_inicio: string
  fecha_fin: string | null
}

export interface UserAccountDetail extends UserAccount {
  historial_roles: RoleHistoryEntry[]
}

/** El backend informa quién ejecuta la operación, porque aún no hay sesión. */
export interface AccountsMeta {
  id_usuario_actual: string | null
}

/** Meta de `GET /usuarios`: el usuario actual y la paginación del servidor. */
export interface AccountsListMeta extends AccountsMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface UserAccountsResponse {
  data: UserAccount[]
  meta: AccountsListMeta
}

export interface UserAccountDetailResponse {
  data: UserAccountDetail
  meta: AccountsMeta
}

/** Asignaciones académicas del periodo activo que dependen del rol Docente. */
export interface ActiveAssignments {
  grupos: number
  materias: number
  examenes: number
  tiene_activas: boolean
}

/** Una página del listado de cuentas, tal como la paginó el servidor. */
export interface UserAccounts {
  accounts: UserAccount[]
  currentUserId: string | null
  page: number
  perPage: number
  total: number
  totalPages: number
}

/** Valor de `?rol=` para las cuentas que aún no tienen rol. */
export const WITHOUT_ROLE_FILTER = 'sin-rol'

/** Filtros del listado, guardados en la URL: búsqueda, rol (id o `sin-rol`) y página. */
export interface AccountFilters {
  q: string
  rol: string | null
  page: number
}

export interface UserAccountWithContext {
  account: UserAccountDetail
  currentUserId: string | null
}

/** Persona tal como la devuelve la verificación en el SIS, antes de tener cuenta. */
export interface SisPerson {
  nombre: string
  paterno: string
  materno: string | null
  tipo: string
  facultad: string
}

/**
 * Cuerpo de `POST /usuarios`: exactamente los campos que declara StoreUserRequest.
 *
 * Un docente invitado envía `sin_codigo_sis` en lugar de `cod_sis`; `id_rol: null` la deja sin rol.
 */
export interface NewUserAccount {
  cod_sis?: string
  sin_codigo_sis?: boolean
  /** Código INV que el administrador vio antes de registrar; el servidor lo vuelve a comprobar. */
  cod_invitado?: string
  id_rol: number | null
  nombre: string
  apellido_paterno: string
  apellido_materno: string | null
  correo: string
  /** Solo viaja en el registro: el servidor guarda su hash y nunca la devuelve. */
  contrasenia_temporal: string
}

/** Rol del docente invitado (nombre interno; en pantalla se muestra con `roleLabel`). */
export const GUEST_ROLE = GUEST_ROLE_NAME

/**
 * Roles cuyo cambio exige revisar asignaciones. El docente invitado no está: su rol no se cambia.
 */
export const TEACHING_ROLES: readonly string[] = ['Docente']

type AccountName = Pick<UserAccount, 'nombre' | 'apellido_paterno' | 'apellido_materno'>

export function accountFullName(account: AccountName): string {
  return [account.nombre, account.apellido_paterno, account.apellido_materno]
    .filter(Boolean)
    .join(' ')
}

export function accountInitials(account: Pick<UserAccount, 'nombre' | 'apellido_paterno'>): string {
  return `${account.nombre.charAt(0)}${account.apellido_paterno.charAt(0)}`.toUpperCase()
}
