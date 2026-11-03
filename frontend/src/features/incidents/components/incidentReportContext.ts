import type { ExamInProgress, InProgressStudent } from '@/features/exams'
import { incidentService } from '../services/incidentService'
import { formatSituationTime } from '../types/exam-situation.types'
import type { ExamIncidentContext } from '../types/incident.types'

/** ¿Se puede reportar a este estudiante ahora? Misma regla en la fila de En curso y en el acceso del menú. */
export function canReport(exam: ExamInProgress, student: InProgressStudent): boolean {
  return exam.estado === 'EN_CURSO' && exam.incidencias_habilitadas !== false
    && !student.expulsado_en && student.id_ambiente !== null
    && ['INGRESO', 'CON_RETRASO'].includes(student.estado_ingreso)
    && (exam.rol_controlador === 'DOCENTE' || student.id_ambiente === exam.id_ambiente_asignado)
}

export interface LoadedIncidentContext {
  context: ExamIncidentContext
  student: InProgressStudent
}

/**
 * Consulta En curso al abrir el formulario para no usar un ambiente ni una hora de una carga anterior.
 * Devuelve null si el examen o el estudiante ya no admiten reportes.
 */
export async function loadIncidentReportContext(
  examId: number,
  studentId: number,
  signal?: AbortSignal,
): Promise<LoadedIncidentContext | null> {
  const exam = await incidentService.examContext(examId, signal)
  const student = exam.estudiantes.find((item) => item.id_estudiante === studentId)
  if (!student || !canReport(exam, student)) return null
  return {
    student,
    context: {
      origin: 'EN_CURSO', examId: exam.id_examen, examName: exam.nombre_examen,
      student, roomId: student.id_ambiente!, roomName: student.ambiente ?? '—',
      timeLabel: formatSituationTime(exam.hora_servidor_en),
    },
  }
}
