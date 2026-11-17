import { DoorOpen } from 'lucide-react'
import { Card, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { DistributionRoom, DistributionStudent } from '../types/roomDistribution.types'
import { RoomStudentRow } from './RoomStudentRow'

interface Props {
  room: DistributionRoom
  students: DistributionStudent[]
  rooms: DistributionRoom[]
  editable: boolean
  onMove: (studentId: number, roomId: number) => void
}

export function RoomCard({ room, students, rooms, editable, onMove }: Props) {
  const full = room.asignados >= room.capacidad
  return (
    <Card className="min-w-0 gap-0 py-0" role="region" aria-label={`Ambiente ${room.nro_aula}`}>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-4">
        <CardTitle><h2 className="flex items-center gap-2 text-base"><DoorOpen aria-hidden="true" className="size-4" />Aula {room.nro_aula}</h2></CardTitle>
        <Badge variant="outline" aria-label={`${room.asignados} asignados de ${room.capacidad} lugares`}
          className={cn(full ? 'border-warn-border bg-warn-soft text-warn-fg' : 'text-muted-foreground')}>
          {room.asignados} / {room.capacidad}
        </Badge>
      </CardHeader>
      {students.length === 0 ? <p className="p-4 text-sm text-muted-foreground">Sin estudiantes asignados.</p> : (
        <ul aria-label={`Estudiantes de ${room.nro_aula}`}>
          {students.map((student) => <RoomStudentRow key={student.id_estudiante} student={student}
            rooms={rooms} editable={editable} onMove={onMove} />)}
        </ul>
      )}
    </Card>
  )
}
