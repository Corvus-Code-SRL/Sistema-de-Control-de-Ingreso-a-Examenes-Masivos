import { Clock } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { EXAM_STATUS, type ExamInProgress } from '@/features/exams'
import { lateEntryPath } from '../paths'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryHeaderActionProps {
  exam: ExamInProgress
}

/** Acceso al ingreso con retraso desde el encabezado de En curso; solo mientras el examen está en curso. */
export function LateEntryHeaderAction({ exam }: LateEntryHeaderActionProps) {
  if (exam.estado !== EXAM_STATUS.EN_CURSO) return null

  return (
    <Button asChild size="sm" className="h-11 sm:h-8">
      <Link to={lateEntryPath(exam.id_examen)}>
        <Clock className="size-4" aria-hidden="true" />
        {LATE_ENTRY_MESSAGES.openExam}
      </Link>
    </Button>
  )
}
