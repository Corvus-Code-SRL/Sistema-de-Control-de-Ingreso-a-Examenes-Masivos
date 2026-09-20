import { Link } from 'react-router-dom'
import { Card } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { RosterStatusBadge } from './RosterStatusBadge'
import { courseTitle, groupLabel, hasRoster, type Course } from '../types/group.types'

interface CourseCardProps {
  course: Course
}

/**
 * Un curso del docente (artboard 2.1).
 *
 * La carrera encabeza la tarjeta porque la misma materia puede aparecer varias
 * veces con distinto grupo: sin ella dos tarjetas serían indistinguibles.
 */
export function CourseCard({ course }: CourseCardProps) {
  const { group, subject } = course

  return (
    <Card className="p-0 transition-colors hover:border-ring">
      <Link
        to={`/cursos/${group.id_grupo}`}
        className="flex h-full flex-col gap-3 p-4 focus-visible:outline-2 focus-visible:outline-ring"
        aria-label={`Abrir ${courseTitle(subject, group)}`}
      >
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 space-y-0.5">
            <p className="truncate text-xs text-muted-foreground">{subject.carrera.nombre}</p>
            <p className="sciem-h3 truncate">{subject.nombre}</p>
            <p className="text-sm font-medium text-muted-foreground">{groupLabel(group)}</p>
          </div>

          <div className="shrink-0">
            <RosterStatusBadge group={group} />
          </div>
        </div>

        <Separator />

        <div className="flex items-baseline gap-1.5 text-sm">
          {hasRoster(group) ? (
            <>
              <span className="sciem-tnum text-lg font-semibold text-brand">
                {group.cantidad_estudiantes}
              </span>
              <span className="text-muted-foreground">
                {group.cantidad_estudiantes === 1 ? 'inscrito' : 'inscritos'}
              </span>
            </>
          ) : (
            <span className="text-muted-foreground">Sin inscritos</span>
          )}
        </div>
      </Link>
    </Card>
  )
}
