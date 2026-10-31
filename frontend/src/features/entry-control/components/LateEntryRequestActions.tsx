import { Button } from '@/components/ui/button'
import type { LateEntryRequest } from '../types/late-entry.types'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryRequestActionsProps {
  request: LateEntryRequest
  busy: boolean
  onApprove: (requestId: number) => void
  onReject: (requestId: number) => void
}

/** Rechazar (secundario) y Autorizar (principal) una solicitud; apilados y a todo el ancho en móvil. */
export function LateEntryRequestActions({ request, busy, onApprove, onReject }: LateEntryRequestActionsProps) {
  const student = request.estudiante.nombre_completo

  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
      <Button
        type="button"
        variant="outline"
        className="h-11 sm:min-w-28"
        disabled={busy}
        aria-label={`${LATE_ENTRY_MESSAGES.reject}: ${student}`}
        onClick={() => onReject(request.id_solicitud)}
      >
        {LATE_ENTRY_MESSAGES.reject}
      </Button>
      <Button
        type="button"
        className="h-11 sm:min-w-28"
        disabled={busy}
        aria-label={`${LATE_ENTRY_MESSAGES.approve}: ${student}`}
        onClick={() => onApprove(request.id_solicitud)}
      >
        {LATE_ENTRY_MESSAGES.approve}
      </Button>
    </div>
  )
}
