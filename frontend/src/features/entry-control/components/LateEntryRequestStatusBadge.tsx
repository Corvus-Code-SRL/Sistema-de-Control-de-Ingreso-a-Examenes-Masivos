import { CheckCircle2, CircleX, Clock } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { LateEntryRequestStatus } from '../types/late-entry.types'
import { LATE_ENTRY_STATUS_LABELS } from './lateEntryMessages'

const STATUS_STYLES: Record<LateEntryRequestStatus, { icon: LucideIcon; className: string }> = {
  PENDIENTE: { icon: Clock, className: 'bg-warn-soft text-warn-fg hover:bg-warn-soft' },
  AUTORIZADA: { icon: CheckCircle2, className: 'bg-ok-soft text-ok-fg hover:bg-ok-soft' },
  RECHAZADA: { icon: CircleX, className: 'bg-danger-soft text-danger-fg hover:bg-danger-soft' },
}

interface LateEntryRequestStatusBadgeProps {
  status: LateEntryRequestStatus
  /** Texto propio del flujo, como «Esperando al docente»; por defecto, el nombre del estado. */
  label?: string
}

/** Estado de una solicitud de ingreso con retraso: siempre con icono y texto. */
export function LateEntryRequestStatusBadge({ status, label }: LateEntryRequestStatusBadgeProps) {
  const { icon: Icon, className } = STATUS_STYLES[status]

  return (
    <Badge className={`gap-1 ${className}`}>
      <Icon className="size-3" aria-hidden="true" />
      {label ?? LATE_ENTRY_STATUS_LABELS[status]}
    </Badge>
  )
}
