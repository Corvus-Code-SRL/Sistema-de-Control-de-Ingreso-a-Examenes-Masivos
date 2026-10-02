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
