/* =========================================================================
 * Tipos base del auxiliar
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

/* =========================================================================
 * Grupos y exámenes asignados
 * ========================================================================= */

/** Examen programado vinculado a un grupo del docente. */
export interface AssistantScheduledExam {
  nombre_examen: string
  fecha: string   // 'YYYY-MM-DD'
}

/** Grupo donde el auxiliar está incorporado, con etiqueta lista para UI. */
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

/* =========================================================================
 * Auxiliar con sus asignaciones (grupos y exámenes)
 * ========================================================================= */

export interface AssistantWithGroups extends Assistant {
  grupos: AssistantGroupAssignment[]
  /** Exámenes donde el auxiliar ya está habilitado. */
  examenes: AssistantExamAssignment[]
  /** Exámenes donde el auxiliar puede ser habilitado (filtrado en backend). */
  examenes_disponibles: AvailableExamForAssistant[]
}

/* =========================================================================
 * Opciones y payloads
 * ========================================================================= */

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

/** Examen al que el auxiliar PUEDE ser habilitado. */
export interface AvailableExamForAssistant {
  id_examen: number
  nombre_examen: string
  fecha: string
}