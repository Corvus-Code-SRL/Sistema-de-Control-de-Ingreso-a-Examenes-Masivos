/** Respuesta de GET /api/examenes/{exam}/reporte-final (HU-051). */

export interface FinalReportHeader {
  id_examen: number
  nombre_examen: string
  materia: string | null
  docente: string | null
  fecha: string
  hora_inicio: string | null
  hora_fin: string | null
  generado_en: string
}

export interface FinalReportAttendance {
  inscritos: number
  a_tiempo: number
  con_retraso: number
  ausentes: number
  sin_clasificar: number
}

export interface FinalReportGroup extends FinalReportAttendance {
  id_grupo: number
  num_grupo: string
}

export interface FinalReportStudent {
  cod_sis: string
  nombre: string
  ambiente: string | null
  hora_ingreso: string | null
  estado: string
}

export interface FinalReportIncident {
  hora: string
  estudiante: string | null
  tipo: string | null
  decision: string
  observacion: string | null
}

export interface FinalReport {
  cabecera: FinalReportHeader
  asistencia: {
    grupos: FinalReportGroup[]
    totales: FinalReportAttendance
  }
  estudiantes: FinalReportStudent[]
  incidencias: FinalReportIncident[]
}
