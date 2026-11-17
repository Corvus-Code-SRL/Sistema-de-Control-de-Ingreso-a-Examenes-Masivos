/**
 * HU-026: invitar docentes colaboradores a un examen propio. El docente que creó el examen elige uno de sus
 * exámenes PROGRAMADOS, busca a un docente de la misma materia y le envía la invitación, que queda PENDIENTE.
 * Aceptarla o rechazarla es de HU-027.
 */

/** Estado guardado de una invitación. SIN_EFECTO: el examen salió de PROGRAMADO sin que se respondiera. */
export type CollaboratorInvitationState = 'PENDIENTE' | 'ACEPTADA' | 'RECHAZADA' | 'SIN_EFECTO'

export interface InvitableExamRoom {
  id_ambiente: number
  nro_aula: string
}

/** Un examen PROGRAMADO propio, con lo que identifica a cada opción del desplegable. */
export interface InvitableExam {
  id_examen: number
  nombre_examen: string
  /** Fecha local del examen, «AAAA-MM-DD». */
  fecha: string | null
  /** Hora local, «HH:MM». */
  hora_inicio: string | null
  hora_fin: string | null
  materia: { id_materia: number; nombre: string; codigo: string | null } | null
  carrera: { id_carrera: number; nombre: string } | null
  ambientes: InvitableExamRoom[]
}

/** Un docente que se puede invitar al examen elegido. */
export interface CollaboratorCandidate {
  id_usuario: string
  nombre_completo: string
  cod_sis: string | null
  /** Ya tiene una invitación pendiente o aceptada a ese examen. */
  ya_invitado: boolean
}

export interface SentInvitationExam {
  id_examen: number
  nombre_examen: string
  materia: string | null
  carrera: string | null
  fecha: string | null
  hora_inicio: string | null
  hora_fin: string | null
  estado: string
}

export interface SentInvitationTeacher {
  id_usuario: string
  nombre_completo: string
  cod_sis: string | null
}

/** Una invitación enviada por el docente. */
export interface SentInvitation {
  id_invitacion: number
  estado: CollaboratorInvitationState
  /** ISO 8601 con desfase. */
  fecha_invitacion: string
  fecha_respuesta: string | null
  fecha_sin_efecto: string | null
  /** Si el colaborador, al aceptar, puede sumar sus propios grupos al examen. */
  permite_agregar_grupos: boolean
  /** Si la lista del examen se muestra dividida por grupo (con su docente) en vez de junta. */
  lista_por_grupo: boolean
  examen: SentInvitationExam
  docente: SentInvitationTeacher
}

export interface SendInvitationPayload {
  id_docente: string
  permite_agregar_grupos: boolean
  /** Solo puede ser true si permite_agregar_grupos es true (el backend responde 422 si no). */
  lista_por_grupo: boolean
}
