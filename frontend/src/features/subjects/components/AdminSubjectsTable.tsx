import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { AdminSubjectSummary } from '../types/subject.types'
import { RecordStatusBadge } from './RecordStatusBadge'

interface AdminSubjectsTableProps {
  subjects: AdminSubjectSummary[]
}

/**
 * Tabla de solo lectura del catálogo institucional: código, nombre y estado.
 *
 * Cada fila representa una materia institucional, no un par materia-carrera.
 */
export function AdminSubjectsTable({ subjects }: AdminSubjectsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Código</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Materia</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Estado</TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {subjects.map((subject) => (
          <TableRow key={subject.id_materia}>
            <TableCell className="sciem-tnum text-xs font-semibold tracking-wider text-muted-foreground">
              {subject.codigo}
            </TableCell>

            <TableCell className="font-medium">{subject.nombre}</TableCell>

            <TableCell>
              <RecordStatusBadge status={subject.estado} />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
