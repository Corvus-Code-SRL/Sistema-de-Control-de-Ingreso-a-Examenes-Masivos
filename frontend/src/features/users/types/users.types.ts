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

export interface UserAccountsResponse {
  data: UserAccount[]
  meta: AccountsMeta
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

export interface UserAccounts {
  accounts: UserAccount[]
  currentUserId: string | null
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

/** Cuerpo de `POST /usuarios`: exactamente los campos que declara StoreUserRequest. */
export interface NewUserAccount {
  cod_sis: string
  nombre: string
  apellido_paterno: string
  apellido_materno: string | null
  correo: string
}

/** Nombre del rol Docente en el catálogo; su cambio exige revisar asignaciones. */
export const TEACHER_ROLE = 'Docente'

type AccountName = Pick<UserAccount, 'nombre' | 'apellido_paterno' | 'apellido_materno'>

export function accountFullName(account: AccountName): string {
  return [account.nombre, account.apellido_paterno, account.apellido_materno]
    .filter(Boolean)
    .join(' ')
}

export function accountInitials(account: Pick<UserAccount, 'nombre' | 'apellido_paterno'>): string {
  return `${account.nombre.charAt(0)}${account.apellido_paterno.charAt(0)}`.toUpperCase()
}
