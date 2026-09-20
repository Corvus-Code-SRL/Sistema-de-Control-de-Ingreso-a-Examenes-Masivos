import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { RosterStatusBadge } from './RosterStatusBadge'
import { hasRoster, type GroupDetail } from '../types/group.types'

interface CourseHeaderProps {
  detail: GroupDetail
}

/**
 * Resumen del curso abierto (artboards 2.3 y 2.4).
 *
 * Muestra solo lo que esta historia conoce: inscritos y estado de la nómina.
 * Auxiliares y próximo examen pertenecen a HU-020 y HU-029 y se añadirán con ellas.
 */
export function CourseHeader({ detail }: CourseHeaderProps) {
  const { group, meta } = detail

  return (
    <Card className="grid gap-4 p-4 sm:grid-cols-3">
      <div className="space-y-0.5">
        <p className="text-xs text-muted-foreground">Inscritos</p>
        <p className="text-2xl font-semibold tabular-nums text-foreground">
          {hasRoster(group) ? (
            group.cantidad_estudiantes
          ) : (
            <>
              <span aria-hidden="true">—</span>
              <span className="sr-only">Sin inscritos</span>
            </>
          )}
        </p>
      </div>

      <div className="space-y-1">
        <p className="text-xs text-muted-foreground">Nómina</p>
        <RosterStatusBadge group={group} />
      </div>

      <div className="space-y-1">
        <p className="text-xs text-muted-foreground">Período</p>
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-sm font-medium text-foreground">
            {group.periodo.nombre_periodo}
          </span>

          {!meta.es_periodo_activo && <Badge variant="secondary">Período anterior</Badge>}
        </div>
      </div>
    </Card>
  )
}
