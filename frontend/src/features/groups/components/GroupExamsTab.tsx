import { CalendarClock, ChevronRight, Plus } from 'lucide-react'
import { Link } from 'react-router-dom'

import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ExamStatusBadge } from '@/features/exams'

import { useGroupExams } from '../hooks/useGroupExams'
import { formatExamDate } from '../utils/examDate'

interface GroupExamsTabProps {
  groupId: number
}

/**
 * Pestaña Exámenes del curso (HU-029): los exámenes que incluyen este grupo.
 *
 * Solo lee. Programar o cambiar un examen se hace desde Exámenes: cada fila lleva a su detalle.
 */
export function GroupExamsTab({ groupId }: GroupExamsTabProps) {
  const { exams, isLoading, isEmpty, error, reload } = useGroupExams(groupId)

  return (
    <Card className="overflow-hidden p-0">
      {isLoading && <LoadingState rows={3} label="Cargando los exámenes del grupo" />}

      {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

      {!isLoading && !error && isEmpty && (
        <EmptyState
          icon={CalendarClock}
          title="Este grupo aún no participa en ningún examen"
          description="Cuando programe un examen que incluya este grupo, aparecerá aquí."
          action={
            <Button asChild size="sm">
              <Link to="/examenes/nuevo">
                <Plus className="size-4" aria-hidden="true" />
                Programar un examen
              </Link>
            </Button>
          }
        />
      )}

      {!isLoading && !error && !isEmpty && (
        <ul aria-label="Exámenes del grupo">
          {exams.map((exam) => (
            <li key={exam.id_examen} className="border-b border-border last:border-b-0">
              <Link
                to={`/examenes/${exam.id_examen}`}
                className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-sunken"
              >
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{exam.nombre_examen}</p>
                  <p className="text-xs text-muted-foreground">{exam.materia?.nombre}</p>
                </div>

                <div className="sciem-tnum text-sm">
                  <p>{formatExamDate(exam.fecha)}</p>
                  <p className="text-xs text-muted-foreground">
                    {exam.hora_inicio}
                    {exam.hora_fin ? `–${exam.hora_fin}` : ''}
                  </p>
                </div>

                <ExamStatusBadge status={exam.estado} />

                <ChevronRight className="size-4 text-muted-foreground" aria-hidden="true" />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}
