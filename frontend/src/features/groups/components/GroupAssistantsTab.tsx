import { UsersRound } from 'lucide-react'
import { Link } from 'react-router-dom'

import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'

import { useGroupAssistants } from '../hooks/useGroupAssistants'
import { formatExamDate } from '../utils/examDate'

interface GroupAssistantsTabProps {
  groupId: number
}

const MANAGE_LINK = (
  <Link to="/mis-auxiliares">Gestionar en Mis auxiliares</Link>
)

/**
 * Pestaña Auxiliares del curso (HU-029): los auxiliares incorporados a este grupo y los exámenes
 * del grupo donde están habilitados.
 *
 * Es de solo lectura: añadir, quitar o habilitar auxiliares vive en «Mis auxiliares» y no se
 * repite aquí.
 */
export function GroupAssistantsTab({ groupId }: GroupAssistantsTabProps) {
  const { assistants, isLoading, isEmpty, error, reload } = useGroupAssistants(groupId)

  return (
    <Card className="overflow-hidden p-0">
      {isLoading && <LoadingState rows={3} label="Cargando los auxiliares del grupo" />}

      {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

      {!isLoading && !error && isEmpty && (
        <EmptyState
          icon={UsersRound}
          title="Este grupo aún no tiene auxiliares"
          description="Incorpore auxiliares a sus grupos desde Mis auxiliares."
          action={
            <Button asChild size="sm" variant="outline">
              {MANAGE_LINK}
            </Button>
          }
        />
      )}

      {!isLoading && !error && !isEmpty && (
        <>
          <ul aria-label="Auxiliares del grupo">
            {assistants.map((assistant) => (
              <li
                key={assistant.id_usuario}
                className="space-y-2 border-b border-border px-4 py-3 last:border-b-0"
              >
                <div className="flex flex-wrap items-baseline gap-x-3">
                  <p className="font-medium">{assistant.nombre_completo}</p>
                  <span className="sciem-tnum text-xs text-muted-foreground">
                    SIS {assistant.cod_sis}
                  </span>
                </div>

                {assistant.examenes.length === 0 ? (
                  <p className="text-xs text-muted-foreground">
                    Aún no está habilitado para ningún examen de este grupo.
                  </p>
                ) : (
                  <ul
                    aria-label={`Exámenes habilitados de ${assistant.nombre_completo}`}
                    className="flex flex-wrap gap-2"
                  >
                    {assistant.examenes.map((exam) => (
                      <li key={exam.id_examen}>
                        <Link
                          to={`/examenes/${exam.id_examen}`}
                          className="inline-flex items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand-deep hover:underline"
                        >
                          {exam.nombre_examen} · {formatExamDate(exam.fecha)}
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>

          <div className="border-t border-border px-4 py-3">
            <Button asChild size="sm" variant="outline">
              {MANAGE_LINK}
            </Button>
          </div>
        </>
      )}
    </Card>
  )
}
