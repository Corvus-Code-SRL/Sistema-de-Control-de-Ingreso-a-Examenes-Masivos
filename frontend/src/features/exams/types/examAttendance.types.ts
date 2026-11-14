import type { Classroom, Exam, EXAM_STATUS, Group } from './exams.types'

export interface ExamAttendanceExam extends Pick<Exam, 'id_examen' | 'nombre_examen' | 'fecha' | 'hora_inicio'> {
  estado: typeof EXAM_STATUS.EN_INGRESO | typeof EXAM_STATUS.EN_CURSO
  hora_fin: string | null
  materia: NonNullable<Exam['materia']> | null
  grupos: Pick<Group, 'id_grupo' | 'num_grupo'>[]
  ambiente: Pick<Classroom, 'id_ambiente' | 'nro_aula'> | null
}
