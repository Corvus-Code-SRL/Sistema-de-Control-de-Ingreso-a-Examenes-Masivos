export interface HistoryExam {
  id_examen: number
  nombre_examen: string
  fecha: string
  materia: string | null
  esperados: number
  presentes: number
  incidencias: number
}

export interface HistoryList {
  examenes: HistoryExam[]
  materias: { id_materia: number; nombre: string }[]
  pagina: number
  paginas: number
  total: number
}

export interface HistorySummary {
  id_examen: number
  nombre_examen: string
  fecha: string
  materia: string | null
  totales: { a_tiempo: number; con_retraso: number; ausentes: number; incidencias: number }
  linea_tiempo: { tipo: 'apertura' | 'inicio' | 'ingreso' | 'retraso' | 'incidencia' | 'fin'; fecha_hora: string; descripcion: string; detalle: string }[]
  ambientes: { id_ambiente: number; nro_aula: string; presentes: number; auxiliares: string[] }[]
}
