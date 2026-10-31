/** Nombre interno del rol del docente invitado, tal como lo devuelve el backend. */
export const GUEST_ROLE_NAME = 'Invitado'

/** Cómo se muestra ese rol en la interfaz. */
export const GUEST_ROLE_LABEL = 'Docente invitado'

/** Nombre de un rol para mostrar: el rol interno «Invitado» se presenta como «Docente invitado». */
export function roleLabel(roleName: string): string {
  return roleName === GUEST_ROLE_NAME ? GUEST_ROLE_LABEL : roleName
}
