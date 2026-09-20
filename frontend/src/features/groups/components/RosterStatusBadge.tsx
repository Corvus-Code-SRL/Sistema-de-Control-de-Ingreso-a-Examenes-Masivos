import { CheckCircle2, FileWarning } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { hasRoster, type Group } from '../types/group.types'

interface RosterStatusBadgeProps {
  group: Group
}

/**
 * Estado de la nómina de un grupo.
 *
 * Un grupo sin nómina es un grupo con cero inscritos, y así se anuncia: con
 * icono y texto. El color acompaña, pero nunca es lo único que lo distingue.
 */
export function RosterStatusBadge({ group }: RosterStatusBadgeProps) {
  if (hasRoster(group)) {
    return (
      <Badge variant="outline">
        <CheckCircle2 className="size-3" aria-hidden="true" />
        Nómina cargada
      </Badge>
    )
  }

  return (
    <Badge variant="secondary">
      <FileWarning className="size-3" aria-hidden="true" />
      Sin nómina
    </Badge>
  )
}

/** Conteo de inscritos, con el guion del diseño cuando no hay nómina. */
export function EnrolledCount({ group }: RosterStatusBadgeProps) {
  if (!hasRoster(group)) {
    return (
      <span className="text-muted-foreground">
        <span aria-hidden="true">—</span>
        <span className="sr-only">Sin inscritos</span>
      </span>
    )
  }

  return <span className="tabular-nums">{group.cantidad_estudiantes}</span>
}
