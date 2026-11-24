import { ArrowRightLeft, ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { DistributionRoom, DistributionStudent } from '../types/roomDistribution.types'
import { DISTRIBUTION_MESSAGES } from '../utils/roomDistributionMessages'

interface Props {
  student: DistributionStudent
  rooms: DistributionRoom[]
  editable: boolean
  onMove: (studentId: number, roomId: number) => void
}

export function RoomStudentRow({ student, rooms, editable, onMove }: Props) {
  const targets = rooms.filter((room) => room.id_ambiente !== student.id_ambiente)
  const onlyTarget = targets.length === 1 ? targets[0] : undefined
  return (
    <li className="flex min-w-0 flex-col gap-2 border-b border-border px-4 py-3 last:border-0 sm:flex-row sm:items-center">
      <div className="min-w-0 flex-1">
        <p className="break-words text-sm font-medium">{student.nombre_completo}</p>
        <p className="text-xs tabular-nums text-muted-foreground">{student.codigo_sis}</p>
      </div>
      {editable && onlyTarget && (
        <Button variant="outline" className="h-11 self-start text-xs" onClick={() => onMove(student.id_estudiante, onlyTarget.id_ambiente)}
          aria-label={`Mover a ${onlyTarget.nro_aula}: ${student.nombre_completo}, SIS ${student.codigo_sis}`}>
          <ArrowRightLeft aria-hidden="true" />Mover a {onlyTarget.nro_aula}
        </Button>
      )}
      {editable && !onlyTarget && targets.length > 0 && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="h-11 self-start text-xs"
              aria-label={`Asignar ambiente: ${student.nombre_completo}, SIS ${student.codigo_sis}`}>
              <ArrowRightLeft aria-hidden="true" />{student.id_ambiente === null ? 'Asignar ambiente' : 'Mover'}
              <ChevronDown aria-hidden="true" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {targets.map((room) => (
              <DropdownMenuItem key={room.id_ambiente} disabled={room.asignados >= room.capacidad}
                className="min-h-11" onSelect={() => onMove(student.id_estudiante, room.id_ambiente)}>
                {room.nro_aula} · {room.asignados} / {room.capacidad}
                {room.asignados >= room.capacidad && ` · ${DISTRIBUTION_MESSAGES.roomFull}`}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </li>
  )
}
