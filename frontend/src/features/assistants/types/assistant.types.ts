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
 * `editable` lo decide el backend según el estado del examen: la vista nunca lo
 * deduce de la hora.
 */
export interface ExamAssistantsData {
  editable: boolean
  ambientes: ExamClassroom[]
  auxiliares: ExamAssistant[]
}