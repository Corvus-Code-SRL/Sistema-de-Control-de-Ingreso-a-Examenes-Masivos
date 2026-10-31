/**
 * A dónde se va después de iniciar sesión.
 *
 * El diseño muestra /docente/inicio, /auxiliar/inicio y /admin/inicio, pero el panel de inicio es de
 * otro sprint y esas rutas no existen. Cada rol va a la pantalla de entrada que ya tiene hoy:
 * - Administrador → /cuentas/nueva (la home de su área).
 * - Docente e Invitado → /materias (la home de su área).
 * - Auxiliar → «/»: el router del área Auxiliar lo lleva a /mis-examenes, su home (HU-09).
 */
export const LOGIN_PATH = '/login'

/** Única pantalla disponible mientras la cuenta tenga una contraseña temporal por cambiar (HU-001). */
export const CHANGE_PASSWORD_PATH = '/cambiar-contrasena'

const HOME_BY_ROLE: Record<string, string> = {
  Administrador: '/cuentas/nueva',
  Docente: '/materias',
  Invitado: '/materias',
  Auxiliar: '/',
}

export function homeForRole(roleName: string | null | undefined): string {
  return (roleName && HOME_BY_ROLE[roleName]) || '/'
}

/** Solo se vuelve a rutas internas distintas del propio login. */
export function safeDestination(from: unknown): string | null {
  return typeof from === 'string' &&
    from.startsWith('/') &&
    !from.startsWith('//') &&
    !from.startsWith('/login')
    ? from
    : null
}

/** La ruta en la que estaba la persona (sesión expirada) y, sin ella, la entrada de su rol. */
export function resolveDestination(from: unknown, roleName: string | null | undefined): string {
  return safeDestination(from) ?? homeForRole(roleName)
}
