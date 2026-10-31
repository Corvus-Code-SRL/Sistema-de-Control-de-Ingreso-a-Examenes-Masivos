import type { EntryContext, EntryRoom, Verification } from './entry-control.types'

/** Quién controla el examen: decide si el ingreso con retraso se solicita o se registra. */
export type ControllerRole = EntryContext['rol_controlador']

export const CONTROLLER_ROLE = {
  DOCENTE: 'DOCENTE',
  AUXILIAR: 'AUXILIAR',
} as const satisfies Record<ControllerRole, ControllerRole>

export type LateEntryRequestStatus = 'PENDIENTE' | 'AUTORIZADA' | 'RECHAZADA'

export const LATE_ENTRY_REQUEST_STATUS = {
  PENDIENTE: 'PENDIENTE',
  AUTORIZADA: 'AUTORIZADA',
  RECHAZADA: 'RECHAZADA',
} as const satisfies Record<LateEntryRequestStatus, LateEntryRequestStatus>

/** Veredictos de la verificación, los mismos del control de ingreso (HU-032). */
export type VerificationVerdict =
  | 'AUTORIZADO'
  | 'NO_ENCONTRADO'
  | 'AUXILIAR_EXAMEN'
  | 'NO_PERTENECE'
  | 'NO_HABILITADO'
  | 'CI_NO_COINCIDE'
  | 'DUPLICADO'
  | 'AULA_NO_ASIGNADA'
  | 'AULA_INCORRECTA'

export interface LateEntryRequest {
  id_solicitud: number
  estado: LateEntryRequestStatus
  estudiante: { id_estudiante: number; cod_sis: string; nombre_completo: string }
  ambiente: EntryRoom
  solicitante: { nombre_completo: string }
  resolutor: { nombre_completo: string } | null
  solicitado_en: string
  /** HH:MM local; es la hora de ingreso si se autoriza. */
  hora_solicitud: string
  resuelto_en: string | null
}

export interface LateEntryVerification extends Verification {
  veredicto: VerificationVerdict
  solicitud_pendiente: LateEntryRequest | null
}

export interface LateEntryRegistration {
  id_estudiante: number
  estado_ingreso: 'CON_RETRASO'
  /** HH:MM local. */
  hora_ingreso: string
  id_ambiente: number
}

export interface LateEntryVerificationInput {
  cod_sis: string
  ci?: string
  /** Solo el auxiliar: su ambiente de control. */
  id_ambiente?: number
}

export interface LateEntryActionInput {
  id_estudiante: number
  ci?: string
  id_ambiente: number
}
