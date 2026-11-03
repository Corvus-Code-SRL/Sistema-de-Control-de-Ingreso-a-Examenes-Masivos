import { CheckCircle2, CircleX } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { LATE_ENTRY_REQUEST_STATUS, type LateEntryRequest } from '../types/late-entry.types'
import { LateEntryNextButton } from './LateEntryNextButton'
import { LateEntryRequestStatusBadge } from './LateEntryRequestStatusBadge'
import { ASSISTANT_LATE_ENTRY_ACTIONS, LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryWaitingStatusProps {
  /** La solicitud enviada, con el estado más reciente que trajo la consulta. */
  request: LateEntryRequest
  onNext: () => void
}

/** Lo que ve el auxiliar después de enviar la solicitud: la espera y luego la decisión del docente. */
export function LateEntryWaitingStatus({ request, onNext }: LateEntryWaitingStatusProps) {
  const approved = request.estado === LATE_ENTRY_REQUEST_STATUS.AUTORIZADA
  const pending = request.estado === LATE_ENTRY_REQUEST_STATUS.PENDIENTE

  return (
    <div className="space-y-3">
      <div aria-live="polite" aria-atomic="true">
        {pending ? (
          <div className="space-y-2 rounded-lg border border-warn-border bg-warn-soft p-3">
            <LateEntryRequestStatusBadge status={request.estado} label={LATE_ENTRY_MESSAGES.waiting} />
            <p className="text-sm font-medium text-warn-fg">{LATE_ENTRY_MESSAGES.waitingDetail(request.hora_solicitud)}</p>
          </div>
        ) : (
          <p className={`flex items-center gap-2 rounded-lg border p-3 text-sm font-semibold ${approved
            ? 'border-ok/30 bg-ok-soft text-ok-fg'
            : 'border-danger/30 bg-danger-soft text-danger-fg'}`}
          >
            {approved
              ? <CheckCircle2 className="size-5 shrink-0" aria-hidden="true" />
              : <CircleX className="size-5 shrink-0" aria-hidden="true" />}
            {approved ? LATE_ENTRY_MESSAGES.waitingApproved : LATE_ENTRY_MESSAGES.waitingRejected}
          </p>
        )}
      </div>
      <div className="@container">
        <div className="flex flex-col gap-2 @xl:flex-row">
        {pending && (
          <Button type="button" className="h-11 w-full @xl:w-auto @xl:flex-1" disabled>
            {ASSISTANT_LATE_ENTRY_ACTIONS.confirmLabel}
          </Button>
        )}
        <LateEntryNextButton onNext={onNext} />
        </div>
      </div>
    </div>
  )
}
