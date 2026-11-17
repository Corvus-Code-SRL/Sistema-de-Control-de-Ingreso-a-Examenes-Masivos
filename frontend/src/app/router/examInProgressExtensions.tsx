import type { ExamInProgressExtensions } from '@/features/exams'
import {
  InProgressIncidentAction,
  InProgressIncidentsCount,
  InProgressSituationAction,
  InProgressSituationsPanel,
} from '@/features/incidents'

const campusTime = new Intl.DateTimeFormat('es-BO', {
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
  timeZone: 'America/La_Paz',
})

/** Composición de las extensiones de En curso. */
export const examInProgressExtensions: ExamInProgressExtensions = {
  headerActions: (exam) => <div className="flex flex-wrap items-center gap-3">
    <InProgressIncidentsCount exam={exam} />
    {exam.estado === 'FINALIZADO' && exam.fin_real_en && (
      <span className="text-xs text-muted-foreground">
        Fin real <time dateTime={exam.fin_real_en}>{campusTime.format(new Date(exam.fin_real_en))}</time>
      </span>
    )}
  </div>,
  studentActions: (student, exam) => <div className="space-y-2">
    <InProgressSituationAction student={student} exam={exam} />
    <InProgressIncidentAction key={`${exam.id_examen}-${student.id_estudiante}`} student={student} exam={exam} />
  </div>,
  panels: (exam) => <InProgressSituationsPanel exam={exam} />,
}
