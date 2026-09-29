/**
 * Tipos de la gestión de auxiliares (HU-08).
 */

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

/** Grupo en el que está asignado un auxiliar, con su etiqueta corta. */
export interface AssistantGroupAssignment {
  id_grupo: number
  label: string
  tiene_examen_programado: boolean
}

/** Auxiliar con sus grupos asignados. */
export interface AssistantWithGroups extends Assistant {
  grupos: AssistantGroupAssignment[]
}

export interface AssistantListResponse {
  data: Assistant[]
}

export interface AssistantWithGroupsListResponse {
  data: AssistantWithGroups[]
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

/** Grupo devuelto por `GET /api/docente/grupos`. */
export interface GroupOption {
  id_grupo: number
  label: string
  num_grupo: string
}