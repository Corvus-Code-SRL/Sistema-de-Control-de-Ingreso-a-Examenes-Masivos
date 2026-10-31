import { CheckCircle2, FileWarning } from 'lucide-react'
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
      <span className="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-medium text-ok-fg">
        <CheckCircle2 className="size-3" aria-hidden="true" />
        Nómina cargada
      </span>
    )
  }

  return (
    <span className="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
      <FileWarning className="size-3" aria-hidden="true" />
      Sin nómina
    </span>
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

  return <span className="sciem-tnum font-medium">{group.cantidad_estudiantes}</span>
}
