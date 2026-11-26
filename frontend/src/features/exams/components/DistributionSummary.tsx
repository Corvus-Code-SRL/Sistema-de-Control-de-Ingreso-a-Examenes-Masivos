import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { RoomDistributionView } from '../types/roomDistribution.types'
import type { distributionSummary } from '../utils/roomDistributionUtils'

interface Props { data: RoomDistributionView; summary: ReturnType<typeof distributionSummary> }

export function DistributionSummary({ data, summary }: Props) {
  const items = [
    { label: 'Habilitados', value: data.total_habilitados, warning: false },
    { label: 'No habilitados', value: data.total_no_habilitados, warning: false },
    { label: 'Capacidad total', value: data.capacidad_total, warning: false },
    { label: 'Asignados', value: summary.assigned, warning: false },
    { label: 'Sin ambiente', value: summary.unassigned, warning: summary.unassigned > 0 },
    { label: 'Lugares faltantes', value: summary.missingSeats, warning: summary.missingSeats > 0 },
  ]
  return (
    <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" aria-live="polite" aria-label="Resumen de distribución">
      {items.map((item) => (
        <Card key={item.label} className={cn('gap-1 p-4', item.warning && 'bg-warn-soft')}>
          <dt className="text-xs text-muted-foreground">{item.label}</dt>
          <dd className={cn('text-2xl font-semibold tabular-nums', item.warning && 'text-warn-fg')}>{item.value}</dd>
        </Card>
      ))}
    </dl>
  )
}
