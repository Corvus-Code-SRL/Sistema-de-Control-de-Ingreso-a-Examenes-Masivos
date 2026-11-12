import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { formatExamShort, formatMoment, initials } from '../lib/invitationFormat'
import type { InvitationSummary } from '../types/invitation.types'
import { InvitationStateBadge } from './InvitationStateBadge'

interface InvitationsListProps {
  invitations: InvitationSummary[]
  selectedId: number | null
  onSelect: (id: number) => void
}

/** Invitaciones de una pestaña, en el orden del servidor (de la más reciente a la más antigua). */
export function InvitationsList({ invitations, selectedId, onSelect }: InvitationsListProps) {
  return (
    <Card className="overflow-hidden p-0">
      <ul aria-label="Invitaciones">
        {invitations.map((invitation) => {
          const selected = invitation.id_invitacion === selectedId

          return (
            <li key={invitation.id_invitacion}>
              <button
                type="button"
                onClick={() => onSelect(invitation.id_invitacion)}
                aria-current={selected ? 'true' : undefined}
                className={cn(
                  'flex w-full items-center gap-3 border-b border-border px-4 py-3.5 text-left transition-colors',
                  selected ? 'bg-brand-soft shadow-[inset_3px_0_0_var(--color-brand)]' : 'hover:bg-sunken'
                )}
              >
                <span
                  aria-hidden="true"
                  className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-deep"
                >
                  {initials(invitation.invitante.nombre_completo)}
                </span>

                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold text-foreground">
                    {invitation.examen.nombre_examen} · {invitation.examen.materia}
                  </span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {formatExamShort(invitation.examen.fecha, invitation.examen.hora_inicio)} · invita{' '}
                    {invitation.invitante.nombre_completo}
                  </span>
                </span>

                <span className="flex shrink-0 flex-col items-end gap-1">
                  <InvitationStateBadge estado={invitation.estado} />
                  <span className="sciem-tnum text-xs text-muted-foreground">
                    {formatMoment(invitation.fecha_respuesta ?? invitation.fecha_invitacion)}
                  </span>
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </Card>
  )
}
