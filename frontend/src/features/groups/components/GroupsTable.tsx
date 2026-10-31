import { Pencil } from 'lucide-react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { GroupDetailAction } from './GroupDetailAction'
import { GroupOwnerCell } from './GroupOwnerCell'
import { EnrolledCount, RosterStatusBadge } from './RosterStatusBadge'
import { groupLabel, type Group } from '../types/group.types'

interface GroupsTableProps {
  groups: Group[]
  onEdit?: (group: Group) => void
}

/** Grupos del par en tabla, para escritorio. */
export function GroupsTable({ groups, onEdit }: GroupsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">N° de grupo</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Docente</TableHead>
          <TableHead className="sciem-overline text-right text-muted-foreground">Inscritos</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Nómina</TableHead>
          <TableHead className="text-right">
            <span className="sr-only">Acciones</span>
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {groups.map((group) => (
          <TableRow key={group.id_grupo} className={cn(!group.es_mio && 'opacity-70')}>
            <TableCell className="font-medium">{groupLabel(group)}</TableCell>

            <TableCell>
              <GroupOwnerCell group={group} />
            </TableCell>

            <TableCell className="text-right">
              <EnrolledCount group={group} />
            </TableCell>

            <TableCell>
              <RosterStatusBadge group={group} />
            </TableCell>

            <TableCell className="text-right">
              <div className="flex items-center justify-end gap-1">
                {/* Un grupo ajeno no se edita desde aquí. */}
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
                <GroupDetailAction group={group} />
              </div>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}