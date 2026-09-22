import { ChevronRight, FolderOpen } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import type { SubjectCareer } from '../types/subject.types'

interface SubjectGroupsActionProps {
  subject: SubjectCareer
  /** En móvil la fila entera es el contexto y basta con el icono. */
  compact?: boolean
}

/**
 * Punto de entrada a los grupos de un par materia-carrera.
 *
 * Solo se ofrece sobre materias propias y activas. Una materia ajena se lista
 * igual —el catálogo es institucional— pero sin acción, y la razón se escribe
 * con texto: un color apagado no la comunica por sí solo.
 */
export function SubjectGroupsAction({ subject, compact = false }: SubjectGroupsActionProps) {
  if (!subject.activa) {
    return (
      <span className="text-xs text-dis-text">No seleccionable</span>
    )
  }

  if (!subject.es_mia) {
    return (
      <span className="flex items-center justify-end gap-1.5 text-xs text-muted-foreground">
        <FolderOpen className="size-3.5 shrink-0" aria-hidden="true" />
        Sin grupos a su cargo
      </span>
    )
  }

  const to = `/carreras/${subject.id_carrera}/materias/${subject.id_materia}/grupos`
  const label = `Ver grupos de ${subject.nombre} en ${subject.carrera.nombre}`

  if (compact) {
    return (
      <Button variant="ghost" size="icon" asChild>
        <Link to={to} aria-label={label}>
          <ChevronRight className="size-4" />
        </Link>
      </Button>
    )
  }

  return (
    <Button variant="outline" size="sm" asChild>
      <Link to={to} aria-label={label}>
        Ver grupos
        <ChevronRight className="size-4" />
      </Link>
    </Button>
  )
}
