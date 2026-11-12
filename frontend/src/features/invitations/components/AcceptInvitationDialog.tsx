import { useState } from 'react'
import { AlertTriangle, Check, Mail } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { capacityCheck, firstName, groupLabel, plural } from '../lib/invitationFormat'
import type { InvitationDetail, MyInvitationGroup } from '../types/invitation.types'
import { isConflict, responseErrorMessage } from './responseError'

interface AcceptInvitationDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  invitation: InvitationDetail
  groups: MyInvitationGroup[]
  onConfirm: () => Promise<void>
  /** El servidor respondió 409: la bandeja recarga y la invitación aparece como vencida. */
  onConflict: (message: string) => void
}

/** Confirmación de aceptar (CA-7), con el aviso de capacidad que no bloquea (CA-9). */
export function AcceptInvitationDialog({
  open,
  onOpenChange,
  invitation,
  groups,
  onConfirm,
  onConflict,
}: AcceptInvitationDialogProps) {
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const capacity = capacityCheck(invitation, groups.map((group) => group.id_grupo))
  const owner = firstName(invitation.invitante.nombre_completo)
  const rooms = invitation.ambientes.map((room) => room.nro_aula)

  async function handleConfirm() {
    setError(null)
    setSubmitting(true)

    try {
      await onConfirm()
    } catch (cause) {
      const message = responseErrorMessage(cause, 'grupos', 'No se pudo aceptar la invitación. Intente de nuevo.')

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
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand">
              <Mail className="size-5" aria-hidden="true" />
            </span>
            <DialogTitle className="flex-1 text-lg leading-tight">¿Aceptar la invitación?</DialogTitle>
          </div>
        </DialogHeader>

        <div className="space-y-4">
          <p className="text-sm">
            Sumará{' '}
            {groups.map((group, index) => (
              <span key={group.id_grupo}>
                {index > 0 && (index === groups.length - 1 ? ' y ' : ', ')}
                el <b>{groupLabel(group)}</b> ({group.inscritos})
              </span>
            ))}{' '}
            al {invitation.examen.nombre_examen} de {invitation.examen.materia}. El examen pasará de{' '}
            {capacity.current} a {plural(capacity.after, 'estudiante', 'estudiantes')}.
          </p>

          {capacity.exceeds && (
            <div
              role="status"
              className="flex items-start gap-2.5 rounded-lg border border-warn-border bg-warn-soft px-3 py-2.5 text-warn-fg"
            >
              <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
              <div className="text-sm">
                <p className="font-semibold">Los ambientes no alcanzan</p>
                <p>
                  {rooms.length === 1 ? `El ${rooms[0]} tiene` : `${joinRooms(rooms)} suman`}{' '}
                  {plural(capacity.capacity, 'lugar', 'lugares')} para {capacity.after} estudiantes. {owner} deberá
                  agregar ambientes; usted puede aceptar igual.
                </p>
              </div>
            </div>
          )}

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
          <Button onClick={handleConfirm} disabled={submitting}>
            <Check className="size-4" />
            {submitting ? 'Aceptando…' : 'Aceptar invitación'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** «El 691A y el 691B». */
function joinRooms(rooms: string[]): string {
  const labels = rooms.map((room) => `el ${room}`)
  const text = `${labels.slice(0, -1).join(', ')} y ${labels[labels.length - 1]}`

  return text.charAt(0).toUpperCase() + text.slice(1)
}
