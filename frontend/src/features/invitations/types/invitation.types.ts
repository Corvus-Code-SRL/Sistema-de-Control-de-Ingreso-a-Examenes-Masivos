/**
 * HU-027: bandeja de invitaciones recibidas. El docente invitado lee y responde las invitaciones que
 * le envió el docente responsable de un examen (HU-026); el estado es el mismo registro que ve quien
 * invitó.
 */

/** Estado guardado. SIN_EFECTO: el examen abrió el control de ingreso sin respuesta (se muestra «Vencida»). */
export type InvitationState = 'PENDIENTE' | 'ACEPTADA' | 'RECHAZADA' | 'SIN_EFECTO'

/** Pestañas de la bandeja. «Cerradas» agrupa las rechazadas y las vencidas. */
export type InvitationTab = 'pendientes' | 'aceptadas' | 'cerradas'

export interface InvitationExam {
  id_examen: number
  nombre_examen: string
  materia: string
  carrera: string | null
  /** «2/2026». */
  periodo: string | null
  /** Fecha local del examen, «AAAA-MM-DD». */
  fecha: string
  /** Hora local, «HH:MM» o «HH:MM:SS». */
  hora_inicio: string
  hora_fin: string | null
}

export interface InvitationPerson {
  nombre_completo: string
}

/** Una fila de la bandeja. */
export interface InvitationSummary {
  id_invitacion: number
  estado: InvitationState
  /** ISO 8601 con desfase (UTC). */
  fecha_invitacion: string
  fecha_respuesta: string | null
  examen: InvitationExam
  invitante: InvitationPerson
}

export interface InvitationRoom {
  id_ambiente: number
  nro_aula: string
  capacidad: number
}

export interface InvitationGroup {
  id_grupo: number
  num_grupo: string
  inscritos: number
}

/** Un grupo del docente invitado en la materia del examen, en el período vigente. */
export interface MyInvitationGroup extends InvitationGroup {
  /** Si puede sumarlo (p. ej. un grupo sin nómina no se puede). */
  seleccionable: boolean
  /** Por qué no es seleccionable, en una frase corta («sin nómina»). */
  motivo: string | null
  /** Si ya forma parte del examen (invitación aceptada). */
  sumado: boolean
}

export interface InvitationDetail extends InvitationSummary {
  mensaje: string | null
  motivo_rechazo: string | null
  /** Cuándo abrió el control de ingreso, si la invitación quedó sin efecto. */
  fecha_sin_efecto: string | null
  ambientes: InvitationRoom[]
  grupos_examen: InvitationGroup[]
  mis_grupos: MyInvitationGroup[]
}

export interface InvitationListResponse {
  data: InvitationSummary[]
  meta?: {
    total_pendientes?: number
    total_aceptadas?: number
    total_cerradas?: number
  }
}

export interface InvitationDetailResponse {
  data: InvitationDetail
}
