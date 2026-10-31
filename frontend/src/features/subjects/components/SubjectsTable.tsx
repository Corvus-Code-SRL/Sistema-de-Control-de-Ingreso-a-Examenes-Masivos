import { BookmarkCheck, Ban } from 'lucide-react'
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
          <TableHead className="sciem-overline text-muted-foreground">Código</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Materia</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Carrera</TableHead>
          <TableHead className="sciem-overline text-right text-muted-foreground">Grupos</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Estado</TableHead>
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
            <TableCell className="sciem-tnum text-xs font-semibold tracking-wider text-muted-foreground">
              {subject.codigo}
            </TableCell>

            <TableCell className="font-medium">{subject.nombre}</TableCell>

            <TableCell className="text-muted-foreground">{subject.carrera.nombre}</TableCell>

            <TableCell className="sciem-tnum text-right font-medium">{subject.cantidad_grupos}</TableCell>

            <TableCell>
              <div className="flex flex-wrap items-center gap-1.5">
                {subject.activa ? (
                  <span className="inline-flex w-fit items-center rounded-full bg-ok-soft px-2 py-0.5 text-xs font-medium text-ok-fg">
                    Activa
                  </span>
                ) : (
                  <span className="inline-flex w-fit items-center gap-1 rounded-full bg-sunken px-2 py-0.5 text-xs font-medium text-muted-foreground">
                    <Ban className="size-3" aria-hidden="true" />
                    Inactiva
                  </span>
                )}

                {subject.es_mia && (
                  <span className="inline-flex w-fit items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand-deep">
                    <BookmarkCheck className="size-3" aria-hidden="true" />
                    Mis materias
                  </span>
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
