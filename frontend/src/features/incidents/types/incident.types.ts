/* =========================================================================
 * HU-045 — Revisar incidencias con doble comprobación
 *
 * Un reporte puede ser de un estudiante (examen regular) o de un postulante
 * (examen de admisión). La vista los trata como "incidencias" en un formato
 * común; el backend los distingue y los expone con el mismo DTO.
 *
 * Solo los reportes de estudiante pueden nacer de un intento de ingreso
 * rechazado (`intento`); en los postulantes ese campo es siempre null.
 * ========================================================================= */

/** Origen del reporte: estudiante o postulante. */
export type IncidentType = 'estudiante' | 'postulante'

/** Estado del reporte en la base de datos. */
export type IncidentState = 'PENDIENTE' | 'APROBADO' | 'RECHAZADO'

/** Filtro de la vista. `revisada` combina aprobadas y rechazadas (como el mockup). */
export type IncidentFilter = 'pendiente' | 'revisada'

/** Persona reportada. Estudiante o postulante. */
export interface IncidentPerson {
  /** Nombre completo. */
  nombre_completo: string
  /** Código SIS (estudiante) o CI (postulante). */
  codigo: string
  /** "Grupo 1" para estudiantes; null para postulantes. */
  grupo: string | null
  /** "Ing. de Sistemas" para estudiantes; null para postulantes. */
  carrera: string | null
}

/** Examen donde ocurrió la incidencia. */
export interface IncidentExam {
  id_examen: number
  nombre_examen: string
  fecha: string        // 'YYYY-MM-DD'
  materia: string | null
}

/** Tipo de falta del catálogo `tipo_falta`. */
export interface IncidentTypeFault {
  id: number
  nombre: string
}

/** Quién reportó la incidencia. */
export interface IncidentReporter {
  nombre_completo: string
  /** "Docente" o "Auxiliar". */
  rol: string
}

/** Quién revisó la incidencia y cuándo. `null` si sigue pendiente. */
export interface IncidentReviewer {
  nombre_completo: string
  fecha_revision: string
  observacion_revision: string | null
}

/** Ambiente asignado al examen donde se detectó la incidencia. */
export interface IncidentClassroom {
  id_ambiente: number
  nro_aula: string
}

/**
 * Intento de ingreso rechazado que originó el reporte.
 *
 * Solo aplica a estudiantes. Los reportes de postulante tienen `intento: null`.
 */
export interface IncidentAttempt {
  id_intento: number
  /** Código de motivo devuelto por el control de ingreso ('DOCUMENTO_NO_VALIDO', ...). */
  motivo: string
  /** Descripción legible del motivo. */
  motivo_legible: string
  /** Observación libre del controlador, si la hubo. */
  observacion: string | null
  fecha_intento: string       // ISO 8601
  /** Hora del intento, 'HH:mm'. */
  hora_intento: string
  /** Ambiente donde se intentó ingresar. */
  ambiente: IncidentClassroom | null
}

/**
 * Incidencia completa (lista y detalle).
 *
 * El `id` es el identificador compuesto que viaja en la URL:
 * `estudiante-{id_reporte_est}` o `postulante-{id_reporte_post}`.
 */
export interface IncidentReport {
  id: string
  tipo: IncidentType
  estado: IncidentState
  persona: IncidentPerson
  examen: IncidentExam
  tipo_falta: IncidentTypeFault
  descripcion: string | null
  evidencia_url: string | null
  evidencia: IncidentEvidence | null   // NUEVO
  reportante: IncidentReporter
  fecha_reporte: string       // ISO 8601
  hora_reporte: string        // 'HH:mm'
  ambiente: IncidentClassroom | null
  /** Solo aplica a estudiantes; en postulantes siempre es null. */
  intento: IncidentAttempt | null
  revisor: IncidentReviewer | null
  en_central_riesgo: boolean
}

/** Respuesta de la lista. */
export interface IncidentListResponse {
  data: IncidentReport[]
  meta: {
    total_pendientes: number
    total_revisadas: number
  }
}

/** Respuesta del detalle y de las acciones. */
export interface IncidentDetailResponse {
  data: IncidentReport
}

export interface IncidentEvidence {
  url: string
  nombre: string
  tipo: string
  peso: string | null
}