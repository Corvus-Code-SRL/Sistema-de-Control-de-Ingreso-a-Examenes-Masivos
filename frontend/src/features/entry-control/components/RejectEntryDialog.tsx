import { useState } from 'react'
import { FormSelect } from '@/components/common/FormSelect'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { RejectionReason } from '../types/entry-control.types'

const reasons: { value: RejectionReason; label: string }[] = [
  { value: 'IDENTIDAD_DUDOSA', label: 'Identidad dudosa o posible suplantación' },
  { value: 'DOCUMENTO_NO_VALIDO', label: 'Documento no válido' },
  { value: 'DECISION_CONTROLADOR', label: 'Decisión del controlador' },
  { value: 'OTRO', label: 'Otro motivo' },
]

interface Props {
  open: boolean
  studentName: string
  busy: boolean
  error: string | null
  onClose: () => void
  onConfirm: (reason: RejectionReason, observation: string) => Promise<boolean>
}

export function RejectEntryDialog({ open, studentName, busy, error, onClose, onConfirm }: Props) {
  const [reason, setReason] = useState<RejectionReason>('IDENTIDAD_DUDOSA')
  const [observation, setObservation] = useState('')

  async function submit() {
    if (await onConfirm(reason, observation)) {
      setObservation('')
      setReason('IDENTIDAD_DUDOSA')
      onClose()
    }
  }

  return <Dialog open={open} onOpenChange={(value) => { if (!value && !busy) onClose() }}>
    <DialogContent>
      <DialogHeader><DialogTitle>Rechazar el ingreso</DialogTitle><DialogDescription>Se registrará el intento de {studentName} con el motivo seleccionado.</DialogDescription></DialogHeader>
      <div className="space-y-4 py-2">
        <div className="space-y-1.5">
          <Label htmlFor="reject-reason">Motivo</Label>
          <FormSelect
            id="reject-reason"
            value={reason}
            placeholder="Seleccionar motivo"
            options={reasons}
            onValueChange={(value) => setReason(value as RejectionReason)}
            disabled={busy}
            className="text-sm"
          />
        </div>
        <div className="space-y-1.5"><Label htmlFor="reject-observation">Observación (opcional)</Label><Textarea id="reject-observation" value={observation} onChange={(event) => setObservation(event.target.value)} maxLength={1000} placeholder="Ej. la foto del carnet no coincide" /></div>
      </div>
      {error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
      <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Volver</Button><Button type="button" variant="destructive" disabled={busy} onClick={() => void submit()}>{busy ? 'Registrando…' : 'Registrar intento'}</Button></DialogFooter>
    </DialogContent>
  </Dialog>
}
