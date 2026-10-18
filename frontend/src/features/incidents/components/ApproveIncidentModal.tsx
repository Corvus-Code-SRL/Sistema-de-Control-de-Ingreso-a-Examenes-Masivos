import { useState } from 'react'
import { ShieldAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { IncidentReview } from '../types/incident.types'

interface ApproveIncidentModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  incident: IncidentReview
  onConfirm: (observacion: string | null) => Promise<void>
}

export function ApproveIncidentModal({
  open,
  onOpenChange,
  incident,
  onConfirm,
}: ApproveIncidentModalProps) {
  const [observacion, setObservacion] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleConfirm() {
    setSubmitting(true)
    try {
      await onConfirm(observacion.trim() === '' ? null : observacion.trim())
      setObservacion('')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
            <div className="flex items-start gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-danger-soft text-danger">
                <ShieldAlert className="size-5" aria-hidden="true" />
            </span>
            <div className="flex-1">
                <DialogTitle className="text-lg leading-tight">
                ¿Aprobar y enviar a la Central de Riesgo?
                </DialogTitle>
            </div>
            </div>
        </DialogHeader>

        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
            Usted es la segunda persona que confirma esta incidencia: la reportó{' '}
            <strong>{incident.reportante.nombre_completo}</strong> en la puerta. Al aprobar,{' '}
            <strong>{incident.persona.nombre_completo}</strong> tendrá un registro de{' '}
            «{incident.tipo_falta.nombre}» en la Central de Riesgo.
            </p>

            <div className="flex flex-col gap-1.5">
            <Label htmlFor="approve-observacion">
                Observación <span className="text-xs font-normal text-muted-foreground">(opcional)</span>
            </Label>
            <Textarea
                id="approve-observacion"
                value={observacion}
                onChange={(e) => setObservacion(e.target.value)}
                placeholder="Motivo de la aprobación (opcional)"
                maxLength={2000}
                className="resize-none"
            />
            </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
            Cancelar
          </Button>
          <Button onClick={handleConfirm} disabled={submitting}>
            <ShieldAlert className="size-4" />
            {submitting ? 'Aprobando…' : 'Aprobar y registrar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}