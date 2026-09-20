import { Ban, BookmarkCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
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
          <span className="font-mono text-xs text-muted-foreground">{subject.codigo}</span>

          {subject.es_mia && (
            <Badge variant="default">
              <BookmarkCheck className="size-3" aria-hidden="true" />
              Mía
            </Badge>
          )}

          {!subject.activa && (
            <Badge variant="secondary">
              <Ban className="size-3" aria-hidden="true" />
              Inactiva
            </Badge>
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
