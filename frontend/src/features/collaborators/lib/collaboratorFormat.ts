import type { CollaboratorInvitationState, InvitableExam } from '../types/collaborator.types'

/** Nombre del estado tal como se muestra al docente. */
export const INVITATION_STATE_LABELS: Record<CollaboratorInvitationState, string> = {
  PENDIENTE: 'Pendiente',
  ACEPTADA: 'Aceptada',
  RECHAZADA: 'Rechazada',
  SIN_EFECTO: 'Sin efecto',
}

/** «2026-12-10» → «10/12/2026». Se reordena el texto: crear una Date correría el día por la zona horaria. */
export function formatExamDate(date: string | null): string {
  const match = date === null ? null : /^(\d{4})-(\d{2})-(\d{2})/.exec(date)

  return match ? `${match[3]}/${match[2]}/${match[1]}` : 'Sin fecha'
}

/** «08:00–09:30»; solo el inicio si falta el fin; vacío si no hay hora. */
export function formatTimeRange(start: string | null, end: string | null): string {
  if (start === null) return ''
  const from = start.slice(0, 5)

  return end === null ? from : `${from}–${end.slice(0, 5)}`
}

/** «Aulas 691A, 692B», «Aula 691A» o «Sin ambiente». */
export function formatRooms(rooms: { nro_aula: string }[]): string {
  if (rooms.length === 0) return 'Sin ambiente'

  return `${rooms.length === 1 ? 'Aula' : 'Aulas'} ${rooms.map((room) => room.nro_aula).join(', ')}`
}

/** Texto de una opción del desplegable: nombre, materia, fecha, hora y ambientes del examen. */
export function examOptionLabel(exam: InvitableExam): string {
  return [
    exam.nombre_examen,
    exam.materia?.nombre ?? 'Sin materia',
    formatExamDate(exam.fecha),
    formatTimeRange(exam.hora_inicio, exam.hora_fin),
    formatRooms(exam.ambientes),
  ]
    .filter((part) => part !== '')
    .join(' · ')
}

/** Fecha y hora de envío en la hora local del navegador: «13/11/2026 09:00». */
export function formatSentAt(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return iso

  const pad = (value: number) => String(value).padStart(2, '0')

  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
