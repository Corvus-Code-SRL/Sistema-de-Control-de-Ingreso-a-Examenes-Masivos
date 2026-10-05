import { ChevronRight } from 'lucide-react'
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
 * Se ofrece sobre todo par activo, tenga o no grupos el docente: sin grupos propios la acción es
 * «Registrar grupo» y lleva a la misma página, donde está «Registrar el primer grupo». Un par
 * inactivo se lista igual —el catálogo es institucional— pero sin acción, y la razón se escribe
 * con texto: un color apagado no la comunica por sí solo.
 */
export function SubjectGroupsAction({ subject, compact = false }: SubjectGroupsActionProps) {
  if (!subject.activa) {
    return (
      <span className="text-xs text-dis-text">No seleccionable</span>
    )
  }

  const to = `/carreras/${subject.id_carrera}/materias/${subject.id_materia}/grupos`
  // Sin grupos propios el par igual se abre: su página de grupos ofrece «Registrar el primer grupo».
  const verb = subject.es_mia ? 'Ver grupos' : 'Registrar grupo'
  const label = `${verb} de ${subject.nombre} en ${subject.carrera.nombre}`

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
        {verb}
        <ChevronRight className="size-4" />
      </Link>
    </Button>
  )
}
