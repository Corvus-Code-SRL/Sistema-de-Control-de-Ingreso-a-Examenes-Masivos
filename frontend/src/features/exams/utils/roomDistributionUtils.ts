import { ApiError } from '@/lib/api-client'
import type {
  DistributionAssignments, DistributionProposal, DistributionStudent, RoomDistributionView,
} from '../types/roomDistribution.types'
import { DISTRIBUTION_MESSAGES } from './roomDistributionMessages'

/** Los errores técnicos de Laravel no se muestran como instrucciones para el docente. */
export function distributionApiError(cause: unknown): ApiError {
  if (!(cause instanceof ApiError)) return new ApiError(0, DISTRIBUTION_MESSAGES.failure)
  if (cause.isNotFound) return new ApiError(404, DISTRIBUTION_MESSAGES.notFound)
  if (cause.isServerError) return new ApiError(cause.status, DISTRIBUTION_MESSAGES.failure)
  return cause
}

export function distributionAssignments(students: DistributionStudent[]): DistributionAssignments {
  return new Map(students.map((student) => [student.id_estudiante, student.id_ambiente]))
}

export function distributionSummary(data: RoomDistributionView, assignments: DistributionAssignments) {
  const occupancy = new Map<number, number>(data.ambientes.map((room) => [room.id_ambiente, 0]))
  let unassigned = 0
  let changes = 0
  for (const student of data.estudiantes) {
    const roomId = assignments.get(student.id_estudiante) ?? null
    if (roomId === null || !occupancy.has(roomId)) unassigned++
    else occupancy.set(roomId, (occupancy.get(roomId) ?? 0) + 1)
    if (roomId !== student.id_ambiente) changes++
  }
  const rooms = data.ambientes.map((room) => ({ ...room, asignados: occupancy.get(room.id_ambiente) ?? 0 }))
  const missingSeats = Math.max(0, data.total_habilitados - data.capacidad_total)
  return {
    rooms, unassigned, changes, missingSeats,
    assigned: data.estudiantes.length - unassigned,
    complete: unassigned === 0 && missingSeats === 0 && rooms.every((room) => room.asignados <= room.capacidad),
  }
}

/** La propuesta no trae versión ni capacidades: un cambio de nómina o de ambientes exige recargar. */
export function proposalMatchesSnapshot(data: RoomDistributionView, proposal: DistributionProposal): boolean {
  if (data.estudiantes.length !== proposal.estudiantes.length) return false
  if (proposal.sin_asignar !== Math.max(0, data.total_habilitados - data.capacidad_total)) return false
  const students = new Map(data.estudiantes.map((student) => [student.id_estudiante, student]))
  const seen = new Set<number>()
  const roomIds = new Set(data.ambientes.map((room) => room.id_ambiente))
  for (const student of proposal.estudiantes) {
    const original = students.get(student.id_estudiante)
    if (!original || seen.has(student.id_estudiante) || original.codigo_sis !== student.codigo_sis
      || original.nombre_completo !== student.nombre_completo) return false
    if (student.id_ambiente !== null && !roomIds.has(student.id_ambiente)) return false
    seen.add(student.id_estudiante)
  }
  return distributionSummary(data, distributionAssignments(proposal.estudiantes)).rooms
    .every((room) => room.asignados <= room.capacidad)
}

export function parseDistributionExamId(value: string | null): number | null {
  if (value === null || !/^\d+$/.test(value)) return null
  const id = Number(value)
  return Number.isSafeInteger(id) && id > 0 ? id : null
}
