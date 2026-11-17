import type { EligibilityStatus, StoredReason } from '../types/eligibleList.types'
import { STORED_REASON_LABELS } from '../types/eligibleList.types'

export function motivoLabel(motivo: string | null | undefined): string {
  if (!motivo) return '—'
  return STORED_REASON_LABELS[motivo as StoredReason] ?? motivo
}

/** Normaliza un texto para búsquedas sin distinguir tildes. */
export function normalizeText(text: string): string {
  return text
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
}

/** Formatea una fecha ISO (YYYY-MM-DD) a DD/MM/YYYY. */
export function formatDate(isoDate: string): string {
  const parts = isoDate.split('-')
  if (parts.length !== 3) return isoDate
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

/** Devuelve el mensaje del banner de solo lectura según el estado del examen. */
export interface ReadOnlyMessage {
  title: string
  description: string
}

export function readOnlyMessage(estado: string): ReadOnlyMessage {
  switch (estado) {
    case 'PROGRAMADO':
      return {
        title: 'El control de ingreso ya se abrió',
        description: 'La lista de habilitados quedó fija. Solo se puede consultar.',
      }
    case 'EN_CURSO':
      return {
        title: 'El control de ingreso ya empezó',
        description: 'La lista de habilitados quedó fija. Solo se puede consultar.',
      }
    case 'FINALIZADO':
      return {
        title: 'El examen ya finalizó',
        description: 'La lista de habilitados quedó fija. Solo se puede consultar.',
      }
    case 'CANCELADO':
      return {
        title: 'El examen está cancelado',
        description: 'La lista de habilitados quedó fija. Solo se puede consultar.',
      }
    default:
      return {
        title: 'Solo lectura',
        description: 'La lista de habilitados es de solo lectura.',
      }
  }
}

/** Etiqueta legible del estado de habilitación. */
export function eligibilityLabel(estado: EligibilityStatus): string {
  switch (estado) {
    case 'HABILITADO': return 'Habilitado'
    case 'NO_HABILITADO': return 'No habilitado'
    case 'CASO_ESPECIAL': return 'Caso especial'
    default: return String(estado)
  }
}

/** Valida el detalle cuando el motivo es OTRO. */
export function validateOtroDetalle(detalle: string): string | null {
  const trimmed = detalle.trim()
  if (trimmed.length < 10) {
    return 'El detalle debe tener al menos 10 caracteres.'
  }
  if (trimmed.length > 500) {
    return 'El detalle no puede superar los 500 caracteres.'
  }
  if (trimmed.startsWith(':')) {
    return 'El detalle no puede empezar con dos puntos (:).'
  }
  return null
}
