import { BookmarkCheck, Ban } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'
import { SubjectGroupsAction } from './SubjectGroupsAction'
import { subjectCareerKey, type SubjectCareer } from '../types/subject.types'

interface SubjectsTableProps {
  subjects: SubjectCareer[]
}

/** Catálogo en tabla, para escritorio. La carrera es columna propia y nunca se omite. */
export function SubjectsTable({ subjects }: SubjectsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Código</TableHead>
          <TableHead>Materia</TableHead>
          <TableHead>Carrera</TableHead>
          <TableHead className="text-right">Grupos</TableHead>
          <TableHead>Estado</TableHead>
          <TableHead className="text-right">
            <span className="sr-only">Acciones</span>
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {subjects.map((subject) => (
          <TableRow
            key={subjectCareerKey(subject)}
            className={cn(!subject.activa && 'opacity-60')}
          >
            <TableCell className="font-mono text-xs text-muted-foreground">
              {subject.codigo}
            </TableCell>

            <TableCell className="font-medium">{subject.nombre}</TableCell>

            <TableCell className="text-muted-foreground">{subject.carrera.nombre}</TableCell>

            <TableCell className="text-right tabular-nums">{subject.cantidad_grupos}</TableCell>

            <TableCell>
              <div className="flex flex-wrap items-center gap-1.5">
                {subject.activa ? (
                  <Badge variant="outline">Activa</Badge>
                ) : (
                  <Badge variant="secondary">
                    <Ban className="size-3" aria-hidden="true" />
                    Inactiva
                  </Badge>
                )}

                {subject.es_mia && (
                  <Badge variant="default">
                    <BookmarkCheck className="size-3" aria-hidden="true" />
                    Mis materias
                  </Badge>
                )}
              </div>
            </TableCell>

            <TableCell className="text-right">
              <SubjectGroupsAction subject={subject} />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
