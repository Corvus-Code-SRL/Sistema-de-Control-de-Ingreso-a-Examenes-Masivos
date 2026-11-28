import { CircleSlash, CircleX } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { UnassignableSubject } from '../types/unassignSubject.types'
import { RecordStatusBadge } from './RecordStatusBadge'

interface UnassignSubjectsTableProps {
  subjects: UnassignableSubject[]
  /** Etiqueta del período, para el encabezado «Grupos en 2/2026». */
  periodLabel: string
  /** Solo el período vigente admite desvincular; los demás son de consulta. */
  canUnassign: boolean
  onAction: (subject: UnassignableSubject) => void
}

function UnassignedBadge() {
  return (
    <span className="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
      <CircleSlash className="size-3" aria-hidden="true" />
      Desvinculada
    </span>
  )
}

function CircleCrossIcon() {
  return (
    <svg
      className="size-4 shrink-0"
      width="17"
      height="17"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="10" />
      <path d="m15 9-6 6" />
      <path d="m9 9 6 6" />
    </svg>
  )
}

/**
 * Tabla de la vista Desvincular. El botón de una materia con grupos queda enfocable pero marcado
 * como deshabilitado (`aria-disabled`): al pulsarlo la página explica por qué no se puede.
 */
export function UnassignSubjectsTable({
  subjects,
  periodLabel,
  canUnassign,
  onAction,
}: UnassignSubjectsTableProps) {
  const headerCell =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground'

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="hidden border-b border-border bg-muted/40 md:table-header-group">
          <tr>
            <th scope="col" className={headerCell}>
              Código
            </th>
            <th scope="col" className={headerCell}>
              Materia
            </th>
            <th scope="col" className={headerCell}>
              Grupos en {periodLabel}
            </th>
            <th scope="col" className={headerCell}>
              Estado
            </th>
            <th scope="col" className={`${headerCell} text-right`}>
              Acción
            </th>
          </tr>
        </thead>

        <tbody className="divide-y divide-border md:divide-y-0">
          {subjects.map((subject) => {
            const unassigned = subject.estado === 'INACTIVO'
            const hasGroups = subject.grupos_count > 0
            const groupsText = hasGroups
              ? `${subject.grupos_count} ${subject.grupos_count === 1 ? 'grupo' : 'grupos'}`
              : 'Sin grupos'

            return (
              <tr
                key={subject.id_materia}
                className="flex flex-col gap-2.5 p-4 md:table-row md:gap-0 md:border-b md:border-border md:p-0 md:last:border-b-0"
              >
                {/* Vista móvil: Código · Materia · Grupos */}
                <td className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1 p-0 md:hidden">
                  <span className="sciem-tnum font-medium">{subject.codigo}</span>
                  <span>{subject.nombre}</span>
                  <span className={cn(!hasGroups && 'text-muted-foreground')}>{groupsText}</span>
                </td>

                {/* Vista escritorio: celdas separadas */}
                <td className="hidden whitespace-nowrap px-4 py-3 font-medium sciem-tnum md:table-cell">
                  {subject.codigo}
                </td>
                <td className="hidden px-4 py-3 md:table-cell">{subject.nombre}</td>
                <td className="hidden whitespace-nowrap px-4 py-3 md:table-cell">
                  {hasGroups ? (
                    groupsText
                  ) : (
                    <span className="text-muted-foreground">Sin grupos</span>
                  )}
                </td>

                {/* Estado */}
                <td className="p-0 md:table-cell md:px-4 md:py-3">
                  {unassigned ? (
                    <UnassignedBadge />
                  ) : (
                    <RecordStatusBadge status={subject.estado} />
                  )}
                </td>

                {/* Acción */}
                <td className="p-0 md:table-cell md:whitespace-nowrap md:px-4 md:py-3 md:text-right">
                  {unassigned || !canUnassign ? (
                    <span
                      className="text-xs text-muted-foreground"
                      aria-label="Sin acción disponible"
                    >
                      —
                    </span>
                  ) : (
                    <Button
                      type="button"
                      variant="destructive"
                      size="sm"
                      aria-label={`Desvincular ${subject.nombre}`}
                      aria-disabled={hasGroups || undefined}
                      className={cn(
                        'w-full justify-center md:w-auto',
                        hasGroups && 'cursor-not-allowed opacity-50'
                      )}
                      onClick={() => onAction(subject)}
                    >
                      <CircleCrossIcon />
                      Desvincular
                    </Button>
                  )}
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
