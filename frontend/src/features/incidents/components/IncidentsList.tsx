import { Card } from '@/components/ui/card'
import { IncidentItem } from './IncidentItem'
import type { IncidentFilter, IncidentReport } from '../types/incident.types'

interface IncidentsListProps {
  incidents: IncidentReport[]
  selectedId: string | null
  onSelect: (id: string) => void
  filter: IncidentFilter
}

export function IncidentsList({ incidents, selectedId, onSelect, filter }: IncidentsListProps) {
  return (
    <Card className="overflow-hidden p-0">
      <div className="border-b border-border px-4 py-3">
        <p className="text-sm text-muted-foreground">
          {filter === 'pendiente'
            ? 'Del más antiguo al más reciente'
            : 'Del más reciente al más antiguo'}
        </p>
      </div>
      <div>
        {incidents.map((incident) => (
          <IncidentItem
            key={incident.id}
            incident={incident}
            isSelected={incident.id === selectedId}
            onSelect={() => onSelect(incident.id)}
            showDecision={filter === 'revisada'}
          />
        ))}
      </div>
    </Card>
  )
}