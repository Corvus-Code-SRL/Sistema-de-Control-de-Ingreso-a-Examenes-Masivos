import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { SubjectCareerAssignment } from '../types/subject.types'
import { RecordStatusBadge } from './RecordStatusBadge'

interface SubjectCareerAssignmentsTableProps {
  assignments: SubjectCareerAssignment[]
}

/** Pares materia-carrera de solo lectura: las asignaciones no se editan desde esta pantalla. */
export function SubjectCareerAssignmentsTable({ assignments }: SubjectCareerAssignmentsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Carrera</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Código</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Materia</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Estado</TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {assignments.map((assignment) => (
          <TableRow key={`${assignment.id_carrera}-${assignment.id_materia}`}>
            <TableCell className="font-medium">{assignment.carrera.nombre}</TableCell>

            <TableCell className="sciem-tnum text-xs font-semibold tracking-wider text-muted-foreground">
              {assignment.materia.codigo}
            </TableCell>

            <TableCell>{assignment.materia.nombre}</TableCell>

            <TableCell>
              <RecordStatusBadge status={assignment.estado} />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
