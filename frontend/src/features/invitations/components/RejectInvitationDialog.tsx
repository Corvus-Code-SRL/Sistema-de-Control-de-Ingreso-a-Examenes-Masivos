import { useState } from 'react'
import { X, XCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { firstName } from '../lib/invitationFormat'
import type { InvitationDetail } from '../types/invitation.types'
import { isConflict, responseErrorMessage } from './responseError'

export const REJECT_REASON_MAX = 500

interface RejectInvitationDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  invitation: InvitationDetail
  onConfirm: (reason: string) => Promise<void>
  onConflict: (message: string) => void
}

/** Rechazar con motivo opcional (CA-8). El examen no cambia. */
export function RejectInvitationDialog({
  open,
  onOpenChange,
  invitation,
  onConfirm,
  onConflict,
}: RejectInvitationDialogProps) {
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleConfirm() {
    setError(null)
    setSubmitting(true)

    try {
      await onConfirm(reason)
      setReason('')
    } catch (cause) {
      const message = responseErrorMessage(cause, 'motivo', 'No se pudo rechazar la invitación. Intente de nuevo.')

      if (isConflict(cause)) {
        onConflict(message)
      } else {
        setError(message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  function handleOpenChange(next: boolean) {
    if (!next) setError(null)
    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <div className="flex items-start gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-danger-soft text-danger">
              <XCircle className="size-5" aria-hidden="true" />
            </span>
            <DialogTitle className="flex-1 text-lg leading-tight">¿Rechazar la invitación?</DialogTitle>
          </div>
        </DialogHeader>

        <div className="space-y-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="reject-invitation-reason">Motivo (opcional)</Label>
            <Textarea
              id="reject-invitation-reason"
              value={reason}
              onChange={(event) => {
                setReason(event.target.value)
                if (error) setError(null)
              }}
              maxLength={REJECT_REASON_MAX}
              aria-invalid={error ? true : undefined}
              className="resize-none"
            />
          </div>
          <p className="text-sm text-muted-foreground">
            {firstName(invitation.invitante.nombre_completo)} verá «Rechazada» y el examen no cambia.
          </p>
          {error && (
            <p role="alert" className="text-sm font-medium text-danger-fg">
              {error}
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={submitting}>
            Volver
          </Button>
          <Button variant="danger" onClick={handleConfirm} disabled={submitting}>
            <X className="size-4" />
            {submitting ? 'Rechazando…' : 'Rechazar invitación'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
