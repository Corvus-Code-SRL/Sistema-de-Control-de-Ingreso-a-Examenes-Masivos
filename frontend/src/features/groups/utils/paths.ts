/** Mis grupos: a donde vuelve el docente desde el detalle de un grupo. */
export const MY_COURSES_PATH = '/mis-cursos'

/** Detalle del grupo (HU-020): la vista a la que se llega desde Mis grupos y desde Grupos de la materia. */
export function groupDetailPath(groupId: number): string {
  return `/grupos/${groupId}`
}

/** Edición del grupo (HU-019): número y período. */
export function groupEditPath(groupId: number): string {
  return `/grupos/${groupId}/editar`
}

/**
 * Carga de nómina (HU-021) con el grupo ya elegido.
 *
 * La carga vive en su propia pantalla, `/cargar-nomina`, a la que se entra desde el menú; este
 * enlace solo la abre con el grupo preseleccionado (por ejemplo, tras registrar un grupo).
 */
export function groupRosterPath(groupId: number): string {
  return `/cargar-nomina?grupo=${groupId}`
}

/** Exámenes asociados al grupo. */
export function groupExamsPath(groupId: number): string {
  return `/grupos/${groupId}/examenes`
}

/** Auxiliares incorporados al grupo. */
export function groupAssistantsPath(groupId: number): string {
  return `/grupos/${groupId}/auxiliares`
}

/**
 * `:idGrupo` de la URL como id de grupo; null si no es un entero positivo de hasta 9 dígitos, que
 * es lo que acepta la API (nunca excede el rango de int4).
 */
export function groupIdFrom(param: string | undefined): number | null {
  if (param === undefined || !/^[1-9]\d{0,8}$/.test(param)) return null

  return Number(param)
}

/** Creación única de HU-24 con contexto inicial de grupo. */
export function groupCreateExamPath(groupId: number): string {
  return `/examenes/nuevo?grupo=${groupId}`
}
