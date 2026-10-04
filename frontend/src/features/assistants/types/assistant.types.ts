/* =========================================================================
 * HU-08 — Auxiliares del docente
 * ========================================================================= */

/** Auxiliar devuelto por la API. */
export interface Assistant {
  id_usuario: string
  nombre: string
  apellido_paterno: string
  apellido_materno: string | null
  nombre_completo: string
  cod_sis: string
  correo: string
}

/** Respuesta de los endpoints de listado y búsqueda simple. */
export interface AssistantListResponse {
  data: Assistant[]
}

/** Respuesta de GET /docente/auxiliares (con grupos y exámenes). */
export interface AssistantWithGroupsListResponse {
  data: AssistantWithGroups[]
}

/** Examen programado vinculado a un grupo del docente. */
export interface AssistantScheduledExam {
  nombre_examen: string
  fecha: string
}

/** Grupo donde el auxiliar está incorporado. */
export interface AssistantGroupAssignment {
  id_grupo: number
  label: string
  tiene_examen_programado: boolean
  examen_programado: AssistantScheduledExam | null
}

/** Examen al que el auxiliar está habilitado. */
export interface AssistantExamAssignment {
  id_examen: number
  nombre_examen: string
  fecha: string
}

/** Examen al que el auxiliar PUEDE ser habilitado. */
export interface AvailableExamForAssistant {
  id_examen: number
  nombre_examen: string
  fecha: string
}

/** Auxiliar tal como lo devuelve GET /docente/auxiliares. */
export interface AssistantWithGroups extends Assistant {
  grupos: AssistantGroupAssignment[]
  examenes: AssistantExamAssignment[]
  examenes_disponibles: AvailableExamForAssistant[]
}

/** Grupo devuelto por GET /docente/grupos para poblar selectores. */
export interface GroupOption {
  id_grupo: number
  label: string
  num_grupo: string
}

/** Payload para añadir/quitar un auxiliar de un grupo. */
export interface AssistantGroupPayload {
  id_usuario: string
  id_grupo: number
}

/** Payload para mover un auxiliar entre grupos. */
export interface MoveAssistantPayload {
  id_usuario: string
  id_grupo_origen: number
  id_grupo_destino: number
}

/* =========================================================================
 * HU-09 — Ambiente del auxiliar por examen
 * ========================================================================= */

/** Ambiente de un examen, como lo devuelve ClassroomResource. */
export interface ExamClassroom {
  id_ambiente: number
  nro_aula: string
  capacidad: number
}

/** Auxiliar habilitado para un examen y el ambiente donde controla el ingreso (HU-09). */
export interface ExamAssistant {
  id_examen: number
  id_usuario: string
  id_ambiente: number | null
  nombre_completo: string
  cod_sis: string
  ambiente: ExamClassroom | null
}

/**
 * Respuesta de GET /examenes/{id}/auxiliares.
 *
 * `estado` y `editable` los decide el backend: la vista nunca los deduce de la hora.
 */
export interface ExamAssistantsData {
  estado: AssistantExamStatus | 'FINALIZADO' | 'CANCELADO'
  editable: boolean
  ambientes: ExamClassroom[]
  auxiliares: ExamAssistant[]
}

/** Estados en los que un examen todavía aparece al auxiliar: el backend no envía otros. */
export type AssistantExamStatus = 'PROGRAMADO' | 'EN_INGRESO' | 'EN_CURSO'

/** Examen que controla el auxiliar, con el ambiente que le asignó el docente (HU-09). */
export interface AssistantExam {
  id_examen: number
  nombre_examen: string
  fecha: string | null
  hora_inicio: string | null
  hora_fin: string | null
  estado: AssistantExamStatus
  materia: string | null
  ambiente: ExamClassroom | null
}
