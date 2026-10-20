/** Mis cursos: a donde vuelve el docente desde el detalle de un grupo. */
export const MY_COURSES_PATH = '/mis-cursos'

/** Detalle del grupo (HU-020): la vista a la que se llega desde Mis cursos y desde Grupos de la materia. */
export function groupDetailPath(groupId: number): string {
  return `/grupos/${groupId}`
}

/** Edición del grupo (HU-019): número y período. */
export function groupEditPath(groupId: number): string {
  return `/grupos/${groupId}/editar`
}

/**
 * Carga de nómina del grupo (HU-021).
 *
 * Hoy la sirve una vista interina que monta el asistente de carga; HU-021 publicará la suya en
 * esta misma URL, así que los enlaces no cambian.
 */
export function groupRosterPath(groupId: number): string {
  return `/grupos/${groupId}/nomina`
}

/** Exámenes programados filtrados por el grupo. */
export function groupExamsPath(groupId: number): string {
  return `/examenes/programados?grupo=${groupId}`
}

/** Mis auxiliares filtrado por el grupo. */
export function groupAssistantsPath(groupId: number): string {
  return `/mis-auxiliares?grupo=${groupId}`
}

/**
 * `:idGrupo` de la URL como id de grupo; null si no es un entero positivo de hasta 9 dígitos, que
 * es lo que acepta la API (nunca excede el rango de int4).
 */
export function groupIdFrom(param: string | undefined): number | null {
  if (param === undefined || !/^[1-9]\d{0,8}$/.test(param)) return null

  return Number(param)
}
