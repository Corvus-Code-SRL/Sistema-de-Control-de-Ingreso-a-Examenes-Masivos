export interface AssistantScheduledExam {
  nombre_examen: string
  fecha: string   // 'YYYY-MM-DD'
}

export interface AssistantGroupAssignment {
  id_grupo: number
  label: string
  tiene_examen_programado: boolean
  examen_programado: AssistantScheduledExam | null
}

export interface GroupOption {
  id_grupo: number
  label: string
  num_grupo: string
}