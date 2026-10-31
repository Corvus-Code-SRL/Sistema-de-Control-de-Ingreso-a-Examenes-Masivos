import { CheckCircle2, Clock, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { IncidentState } from '../types/incident.types'

interface IncidentStateBadgeProps {
  estado: IncidentState
}

export function IncidentStateBadge({ estado }: IncidentStateBadgeProps) {
  if (estado === 'PENDIENTE') {
    return (
      <Badge className="gap-1 bg-warn-soft text-warn-fg hover:bg-warn-soft">
        <Clock className="size-3" aria-hidden="true" />
        Pendiente
      </Badge>
    )
  }

  if (estado === 'APROBADO') {
    return (
      <Badge className="gap-1 bg-ok-soft text-ok-fg hover:bg-ok-soft">
        <CheckCircle2 className="size-3" aria-hidden="true" />
        Aprobado
      </Badge>
    )
  }

  return (
    <Badge className="gap-1 bg-neutral-soft text-neutral-fg hover:bg-neutral-soft">
      <XCircle className="size-3" aria-hidden="true" />
      Rechazado
    </Badge>
  )
}
