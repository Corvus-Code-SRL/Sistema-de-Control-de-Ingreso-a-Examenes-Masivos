import { Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { GroupDetailAction } from './GroupDetailAction'
import { EnrolledCount, RosterStatusBadge } from './RosterStatusBadge'
import { groupLabel, type Group } from '../types/group.types'

interface GroupListItemProps {
  group: Group
  onEdit?: (group: Group) => void
}

/** Grupo del par en móvil. */
export function GroupListItem({ group, onEdit }: GroupListItemProps) {
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

      <div className="flex shrink-0 items-center gap-1">
        {group.es_mio && onEdit && (
          <Button
            variant="ghost"
            size="icon"
            aria-label={`Editar grupo ${groupLabel(group)}`}
            onClick={() => onEdit(group)}
          >
            <Pencil className="size-4" aria-hidden="true" />
          </Button>
        )}
        <GroupDetailAction group={group} compact />
      </div>
    </li>
  )
}