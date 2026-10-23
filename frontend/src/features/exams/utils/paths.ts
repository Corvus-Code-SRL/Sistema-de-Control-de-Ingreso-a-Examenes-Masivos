/** Detalle único del examen, con un origen opcional para regresar al grupo. */
export function examDetailPath(examId: number, groupId?: number): string {
  const path = `/examenes/${examId}`
  return groupId === undefined ? path : `${path}?grupo=${groupId}`
}
