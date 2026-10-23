import { Card } from '@/components/ui/card'
import { IncidentItem } from './IncidentItem'
import type { IncidentFilter, IncidentReview } from '../types/incident.types'

interface IncidentsListProps {
  incidents: IncidentReview[]
  selectedId: string | null
  onSelect: (id: string) => void
  filter: IncidentFilter
}

export function IncidentsList({ incidents, selectedId, onSelect, filter }: IncidentsListProps) {
  return (
    <Card className="overflow-hidden p-0">
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
