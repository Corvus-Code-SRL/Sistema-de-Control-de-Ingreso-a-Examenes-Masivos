import { CheckCircle2, Clock, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { STATE_LABEL } from '../lib/invitationFormat'
import type { InvitationState } from '../types/invitation.types'

const STYLE: Record<InvitationState, string> = {
  PENDIENTE: 'bg-warn-soft text-warn-fg hover:bg-warn-soft',
  ACEPTADA: 'bg-ok-soft text-ok-fg hover:bg-ok-soft',
  RECHAZADA: 'bg-neutral-soft text-neutral-fg hover:bg-neutral-soft',
  SIN_EFECTO: 'bg-neutral-soft text-neutral-fg hover:bg-neutral-soft',
}

const ICON = {
  PENDIENTE: Clock,
  ACEPTADA: CheckCircle2,
  RECHAZADA: XCircle,
  SIN_EFECTO: Clock,
} as const

/** Estado con icono y texto, nunca solo color. */
export function InvitationStateBadge({ estado, suffix }: { estado: InvitationState; suffix?: string }) {
  const Icon = ICON[estado]

  return (
    <Badge className={`gap-1 ${STYLE[estado]}`}>
      <Icon className="size-3" aria-hidden="true" />
      {STATE_LABEL[estado]}
      {suffix ? ` ${suffix}` : ''}
    </Badge>
  )
}
