import { Ban, BookmarkCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { SubjectGroupsAction } from './SubjectGroupsAction'
import type { SubjectCareer } from '../types/subject.types'

interface SubjectListItemProps {
  subject: SubjectCareer
}

/**
 * Entrada del catálogo en móvil.
 *
 * La carrera se conserva junto al conteo de grupos: sin ella dos filas de la
 * misma materia serían idénticas.
 */
export function SubjectListItem({ subject }: SubjectListItemProps) {
  return (
    <li
      className={cn(
        'flex items-center gap-3 border-b border-border px-4 py-3 last:border-b-0',
        !subject.activa && 'opacity-60'
      )}
    >
      <div className="min-w-0 flex-1 space-y-0.5">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="sciem-tnum text-xs font-semibold tracking-wider text-muted-foreground">{subject.codigo}</span>

          {subject.es_mia && (
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand-deep">
              <BookmarkCheck className="size-3" aria-hidden="true" />
              Mía
            </span>
          )}

          {!subject.activa && (
            <span className="inline-flex items-center gap-1 rounded-full bg-sunken px-2 py-0.5 text-xs font-medium text-muted-foreground">
              <Ban className="size-3" aria-hidden="true" />
              Inactiva
            </span>
          )}
        </div>

        <p className="truncate text-sm font-medium text-foreground">{subject.nombre}</p>

        <p className="truncate text-xs text-muted-foreground">
          {subject.carrera.nombre} · {subject.cantidad_grupos}{' '}
          {subject.cantidad_grupos === 1 ? 'grupo' : 'grupos'}
        </p>
      </div>

      <div className="shrink-0">
        <SubjectGroupsAction subject={subject} compact />
      </div>
    </li>
  )
}
