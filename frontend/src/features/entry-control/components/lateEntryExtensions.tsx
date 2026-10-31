import { EXAM_STATUS, type ExamInProgressExtensions } from '@/features/exams'
import { CONTROLLER_ROLE } from '../types/late-entry.types'
import { LateEntryHeaderAction } from './LateEntryHeaderAction'
import { LateEntryRequestNotice } from './LateEntryRequestNotice'

/** Lo que HU-037 aporta a la vista En curso (HU-038-A) por sus puntos de extensión. */
export const lateEntryExtensions = {
  headerActions: (exam) => <LateEntryHeaderAction exam={exam} />,
  panels: (exam) =>
    exam.rol_controlador === CONTROLLER_ROLE.DOCENTE && exam.estado === EXAM_STATUS.EN_CURSO
      ? <LateEntryRequestNotice examId={exam.id_examen} />
      : null,
} satisfies Required<Pick<ExamInProgressExtensions, 'headerActions' | 'panels'>>
