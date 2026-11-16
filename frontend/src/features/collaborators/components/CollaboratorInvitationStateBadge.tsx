import { Ban, CheckCircle2, Clock, XCircle, type LucideIcon } from 'lucide-react'
import { INVITATION_STATE_LABELS } from '../lib/collaboratorFormat'
import type { CollaboratorInvitationState } from '../types/collaborator.types'

const STATE_STYLES: Record<CollaboratorInvitationState, { icon: LucideIcon; className: string }> = {
  PENDIENTE: { icon: Clock, className: 'bg-warn-soft text-warn-fg' },
  ACEPTADA: { icon: CheckCircle2, className: 'bg-ok-soft text-ok-fg' },
  RECHAZADA: { icon: XCircle, className: 'bg-danger-soft text-danger-fg' },
  SIN_EFECTO: { icon: Ban, className: 'bg-muted text-muted-foreground' },
}

interface CollaboratorInvitationStateBadgeProps {
  state: CollaboratorInvitationState
}

/** Estado de una invitación enviada: siempre con icono y texto, el color nunca es lo único que lo distingue. */
export function CollaboratorInvitationStateBadge({ state }: CollaboratorInvitationStateBadgeProps) {
  const { icon: Icon, className } = STATE_STYLES[state]

  return (
    <span className={`inline-flex w-fit shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>
      <Icon className="size-3" aria-hidden="true" />
      {INVITATION_STATE_LABELS[state]}
    </span>
  )
}
