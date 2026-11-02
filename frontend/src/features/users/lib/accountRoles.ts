import { GUEST_ROLE, type Role, type UserAccount } from '../types/users.types'
import { isGuestCode } from './sisCode'

/** Aviso de las cuentas de docente invitado: su rol lo fija el sistema y no se cambia. */
export const GUEST_ROLE_LOCKED_MESSAGE = 'El rol de docente invitado no se puede cambiar.'

type AccountLike = Pick<UserAccount, 'cod_sis'> & Partial<Pick<UserAccount, 'es_invitado'>>

/** ¿Es una cuenta de docente invitado? Lo dice el servidor; el prefijo INV cubre una cuenta aún sin cargar. */
export function isGuestAccount(account: AccountLike): boolean {
  return account.es_invitado ?? isGuestCode(account.cod_sis)
}

/**
 * Roles que admite una cuenta: la de un docente invitado solo puede ser Invitado y una cuenta con
 * código SIS nunca lo es. Es el mismo criterio que aplica el servidor al asignar el rol.
 */
export function rolesAllowedFor(roles: readonly Role[], account: AccountLike): Role[] {
  const guest = isGuestAccount(account)

  return roles.filter((role) => (role.nombre_rol === GUEST_ROLE) === guest)
}
