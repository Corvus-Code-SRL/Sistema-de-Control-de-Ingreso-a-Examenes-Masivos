import { cn } from '@/lib/utils'
import { GroupDetailAction } from './GroupDetailAction'
import { EnrolledCount, RosterStatusBadge } from './RosterStatusBadge'
import { groupLabel, type Group } from '../types/group.types'

interface GroupListItemProps {
  group: Group
}

/** Grupo del par en móvil. */
export function GroupListItem({ group }: GroupListItemProps) {
  return (
    <li
      className={cn(
        'flex items-center gap-3 border-b border-border px-4 py-3 last:border-b-0',
        !group.es_mio && 'opacity-70'
      )}
    >
      <div className="min-w-0 flex-1 space-y-1">
        <p className="text-sm font-medium text-foreground">{groupLabel(group)}</p>

        <p className="truncate text-xs text-muted-foreground">
          {group.docente.nombre_completo}
          {group.es_mio && ' (usted)'} ·{' '}
          <EnrolledCount group={group} />
          {group.cantidad_estudiantes > 0 && ' inscritos'}
        </p>

        <RosterStatusBadge group={group} />
      </div>

      <div className="shrink-0">
        <GroupDetailAction group={group} compact />
      </div>
    </li>
  )
}
