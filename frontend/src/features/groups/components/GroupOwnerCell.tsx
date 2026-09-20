import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import type { Group } from '../types/group.types'

interface GroupOwnerCellProps {
  group: Group
}

/**
 * Docente dueño del grupo.
 *
 * El listado incluye grupos de otros docentes, así que el nombre siempre se
 * muestra y los propios se marcan con «(usted)» para no tener que compararlos.
 */
export function GroupOwnerCell({ group }: GroupOwnerCellProps) {
  return (
    <div className="flex items-center gap-2">
      <Avatar className="size-6 shrink-0">
        <AvatarFallback className="text-[10px]">{initials(group.docente.nombre_completo)}</AvatarFallback>
      </Avatar>

      <span className="truncate text-sm">{group.docente.nombre_completo}</span>

      {group.es_mio && <span className="shrink-0 text-xs text-muted-foreground">(usted)</span>}
    </div>
  )
}

function initials(fullName: string): string {
  return fullName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}
