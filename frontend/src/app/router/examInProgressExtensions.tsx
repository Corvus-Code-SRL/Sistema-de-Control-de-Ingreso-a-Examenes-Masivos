import type { ExamInProgressExtensions } from '@/features/exams'
import { FinishExamAction } from '@/features/exams'
import {
  InProgressIncidentAction,
  InProgressIncidentsCount,
  InProgressSituationAction,
  InProgressSituationsPanel,
} from '@/features/incidents'

/**
 * Punto de composición de HU-037, HU-038-B, HU-042 y HU-057 sobre En curso.
 * Cada feature aporta componentes aquí sin editar ExamInProgressPage.
 */
export const examInProgressExtensions: ExamInProgressExtensions = {
  headerActions: (exam) => <div className="flex flex-wrap items-center gap-3">
    <InProgressIncidentsCount exam={exam} />
    <FinishExamAction key={exam.id_examen} exam={exam} />
  </div>,
  studentActions: (student, exam) => <div className="space-y-2">
    <InProgressSituationAction student={student} exam={exam} />
    <InProgressIncidentAction key={`${exam.id_examen}-${student.id_estudiante}`} student={student} exam={exam} />
  </div>,
  panels: (exam) => <InProgressSituationsPanel exam={exam} />,
}
