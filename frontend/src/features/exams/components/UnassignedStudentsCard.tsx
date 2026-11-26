import { Card, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { DistributionRoom, DistributionStudent } from '../types/roomDistribution.types'
import { DISTRIBUTION_MESSAGES } from '../utils/roomDistributionMessages'
import { RoomStudentRow } from './RoomStudentRow'

interface Props {
  students: DistributionStudent[]
  rooms: DistributionRoom[]
  editable: boolean
  onMove: (studentId: number, roomId: number) => void
}

export function UnassignedStudentsCard({ students, rooms, editable, onMove }: Props) {
  if (students.length === 0) return null
  return (
    <Card className="gap-0 py-0" role="region" aria-label={DISTRIBUTION_MESSAGES.unassigned}>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-4">
        <CardTitle><h2 className="text-base">{DISTRIBUTION_MESSAGES.unassigned}</h2></CardTitle>
        <Badge variant="outline">{students.length}</Badge>
      </CardHeader>
      <ul aria-label="Estudiantes sin ambiente">
        {students.map((student) => <RoomStudentRow key={student.id_estudiante} student={student}
          rooms={rooms} editable={editable} onMove={onMove} />)}
      </ul>
    </Card>
  )
}
