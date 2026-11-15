import type { Classroom, Exam, EXAM_STATUS, Group } from './exams.types'

export interface ExamAttendanceExam extends Pick<Exam, 'id_examen' | 'nombre_examen' | 'fecha' | 'hora_inicio'> {
  estado: typeof EXAM_STATUS.EN_INGRESO | typeof EXAM_STATUS.EN_CURSO
  hora_fin: string | null
  materia: NonNullable<Exam['materia']> | null
  grupos: Pick<Group, 'id_grupo' | 'num_grupo'>[]
  ambiente: Pick<Classroom, 'id_ambiente' | 'nro_aula'> | null
}

export const ATTENDANCE_STATUSES = [
  'SIN_REGISTRO', 'AUSENTE', 'A_TIEMPO', 'CON_RETRASO', 'EXPULSADO', 'NO_HABILITADO', 'SIN_CLASIFICAR',
] as const

export type AttendanceStatus = typeof ATTENDANCE_STATUSES[number]
export type AttendanceCareer = { id_carrera: number; nombre: string }

export interface ExamAttendanceStudent {
  id_estudiante: number
  nombre_completo: string
  cod_sis: string
  ci: string | null
  carrera: AttendanceCareer | null
  id_ambiente: number | null
  ambiente: string | null
  estado: AttendanceStatus
  hora_ingreso: string | null
  expulsado_en: string | null
}

export interface ExamAttendanceDetail {
  id_examen: number
  estado: ExamAttendanceExam['estado']
  id_ambiente_asignado: number | null
  ambientes: NonNullable<ExamAttendanceExam['ambiente']>[]
  carreras: AttendanceCareer[]
  estudiantes: ExamAttendanceStudent[]
  paginacion: { current_page: number; last_page: number; per_page: number; total: number }
  totales: {
    total: number
    registrados: number
    sin_confirmar: number
    sin_registro: number
    ausentes: number
    a_tiempo: number
    con_retraso: number
    expulsados: number
    no_habilitados: number
    sin_clasificar: number
  }
}
