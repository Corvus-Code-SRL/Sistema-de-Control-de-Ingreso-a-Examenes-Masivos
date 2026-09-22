import { SquarePen } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { AdminSubjectSummary } from '../types/subject.types'

interface AdminSubjectsTableProps {
  subjects: AdminSubjectSummary[]
}

/**
 * Tabla administrativa de materias.
 *
 * Cada fila representa una materia institucional, no un par materia-carrera.
 */
export function AdminSubjectsTable({ subjects }: AdminSubjectsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">
            Código
          </TableHead>

          <TableHead className="sciem-overline text-muted-foreground">
            Materia
          </TableHead>

          <TableHead className="text-right">
            <span className="sr-only">Acciones</span>
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {subjects.map((subject) => (
          <TableRow key={subject.id_materia}>
            <TableCell className="sciem-tnum text-xs font-semibold tracking-wider text-muted-foreground">
              {subject.codigo}
            </TableCell>

            <TableCell className="font-medium">
              {subject.nombre}
            </TableCell>

            <TableCell className="text-right">
              <Button variant="outline" size="sm" asChild>
                <Link
                  to={`/materias/${subject.id_materia}/editar`}
                  aria-label={`Editar ${subject.nombre}`}
                >
                  <SquarePen className="size-4" />
                  Editar
                </Link>
              </Button>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}