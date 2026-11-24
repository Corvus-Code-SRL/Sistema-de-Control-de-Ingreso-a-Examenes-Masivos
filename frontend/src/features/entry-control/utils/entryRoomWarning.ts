import { ApiError } from '@/lib/api-client'
import type { EntryRoomWarning } from '../types/entry-control.types'

/** Veredicto del 409 con el que el servidor pide confirmar un aviso de ambiente. */
export const ROOM_WARNING_VERDICT = 'AVISO_AMBIENTE'

interface RoomWarningBody {
  veredicto: typeof ROOM_WARNING_VERDICT
  avisos_ambiente: EntryRoomWarning[]
}

function isRoomWarningBody(body: unknown): body is RoomWarningBody {
  if (typeof body !== 'object' || body === null) return false
  const candidate = body as Partial<RoomWarningBody>
  return candidate.veredicto === ROOM_WARNING_VERDICT && Array.isArray(candidate.avisos_ambiente)
}

/** Los avisos de un error de registro, o null si el error es de otra clase. */
export function roomWarningsOf(cause: unknown): EntryRoomWarning[] | null {
  if (!(cause instanceof ApiError) || cause.status !== 409) return null
  return isRoomWarningBody(cause.responseBody) ? cause.responseBody.avisos_ambiente : null
}
