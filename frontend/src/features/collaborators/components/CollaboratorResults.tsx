import { CheckCircle2, SearchX, UserRoundPlus } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { Button } from '@/components/ui/button'
import type { CollaboratorCandidate } from '../types/collaborator.types'

/** Texto exacto de la historia cuando ningún docente de la materia coincide con la búsqueda. */
export const NO_COLLABORATORS_FOUND = 'No hay docentes de esta materia que coincidan con la búsqueda'

interface CollaboratorResultsProps {
  results: CollaboratorCandidate[]
  onInvite: (candidate: CollaboratorCandidate) => void
}

/** Docentes encontrados. Uno que ya tiene una invitación pendiente o aceptada se marca y no se puede invitar. */
export function CollaboratorResults({ results, onInvite }: CollaboratorResultsProps) {
  if (results.length === 0) {
    return <EmptyState icon={SearchX} title={NO_COLLABORATORS_FOUND} description="Pruebe con otro nombre o código SIS." />
  }

  return (
    <ul aria-label="Docentes encontrados" className="divide-y rounded-xl border bg-card">
      {results.map((candidate) => (
        <li key={candidate.id_usuario} className="flex flex-wrap items-center justify-between gap-3 p-3">
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{candidate.nombre_completo}</p>
            {candidate.cod_sis && <p className="text-xs text-muted-foreground">Código SIS {candidate.cod_sis}</p>}
          </div>

          {candidate.ya_invitado ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-medium text-ok-fg">
              <CheckCircle2 className="size-3" aria-hidden="true" />
              Ya invitado
            </span>
          ) : (
            <Button type="button" variant="outline" className="h-11" onClick={() => onInvite(candidate)}>
              <UserRoundPlus className="size-4" aria-hidden="true" />
              Invitar
              <span className="sr-only"> a {candidate.nombre_completo}</span>
            </Button>
          )}
        </li>
      ))}
    </ul>
  )
}
