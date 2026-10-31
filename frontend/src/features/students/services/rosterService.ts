import { apiPost } from '@/lib/api-client'
import type { RosterConfirmationResponse, RosterPreviewResponse } from '../types/roster.types'

/**
 * Carga de nómina contra la API del módulo Academic.
 *
 * Son dos pasos deliberadamente separados: el preview solo lee el archivo y
 * devuelve un token, y el confirm es lo único que escribe en la base de datos.
 */

/** El backend valida `max:10240` KB sobre el archivo subido. */
export const MAX_ROSTER_FILE_BYTES = 10 * 1024 * 1024
export const ALLOWED_ROSTER_EXTENSIONS = ['csv', 'xlsx'] as const

/**
 * Comprueba el archivo antes de gastar una subida.
 *
 * Devuelve el motivo del rechazo, o `null` si el archivo puede enviarse. Son las
 * mismas reglas que aplica PreviewStudentRosterRequest, adelantadas al cliente.
 */
export function validateRosterFile(file: File): string | null {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''

  if (!ALLOWED_ROSTER_EXTENSIONS.some((allowed) => allowed === extension)) {
    return 'El archivo debe tener formato CSV o XLSX.'
  }

  if (file.size === 0) {
    return 'El archivo está vacío.'
  }

  if (file.size > MAX_ROSTER_FILE_BYTES) {
    return 'La nómina no puede superar los 10 MB.'
  }

  return null
}

/** Lee el archivo y devuelve la clasificación de sus filas. No persiste nada. */
export async function previewStudentRoster(
  groupId: number,
  file: File,
  signal?: AbortSignal
): Promise<RosterPreviewResponse> {
  const body = new FormData()
  body.append('archivo', file)

  return apiPost<RosterPreviewResponse>(`/grupos/${groupId}/nomina/preview`, body, { signal })
}

/**
 * Incorpora al grupo las filas del preview identificado por `token`.
 *
 * El token se consume: repetirlo responde 404. `groupId` debe ser el mismo con
 * el que se generó el preview, o el backend responde 422.
 */
export async function confirmStudentRoster(
  groupId: number,
  token: string,
  signal?: AbortSignal
): Promise<RosterConfirmationResponse> {
  return apiPost<RosterConfirmationResponse>(`/grupos/${groupId}/nomina/confirm`, { token }, { signal })
}
