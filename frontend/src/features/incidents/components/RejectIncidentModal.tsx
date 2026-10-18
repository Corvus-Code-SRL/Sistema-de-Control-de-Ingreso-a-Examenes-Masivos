import { useState } from 'react'
import { X, XCircle } from 'lucide-react'
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
import { ApiError } from '@/lib/api-client'
import type { IncidentReview } from '../types/incident.types'

interface RejectIncidentModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  incident: IncidentReview
  onConfirm: (observacion: string) => Promise<void>
}

export function RejectIncidentModal({
  open,
  onOpenChange,
  incident,
  onConfirm,
}: RejectIncidentModalProps) {
  const [observacion, setObservacion] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleConfirm() {
    setError(null)

    if (observacion.trim() === '') {
      setError('La observación es obligatoria.')
      return
    }

    setSubmitting(true)
    try {
      await onConfirm(observacion.trim())
      setObservacion('')
    } catch (cause) {
      if (cause instanceof ApiError && cause.errors.observacion?.length) {
        setError(cause.errors.observacion[0])
      } else {
        setError('No se pudo rechazar la incidencia. Intente de nuevo.')
      }
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
                <XCircle className="size-5" aria-hidden="true" />
            </span>
            <div className="flex-1">
                <DialogTitle className="text-lg leading-tight">
                Rechazar la incidencia
                </DialogTitle>
            </div>
            </div>
        </DialogHeader>

        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
            El reporte no entrará a la Central de Riesgo. Explique por qué para que quede constancia.
            </p>

            <div className="flex flex-col gap-1.5">
            <Label htmlFor="reject-observacion">
                Observación <span className="text-danger">*</span>
            </Label>
            <Textarea
                id="reject-observacion"
                value={observacion}
                onChange={(e) => {
                setObservacion(e.target.value)
                if (error) setError(null)
                }}
                placeholder="Motivo del rechazo"
                maxLength={2000}
                aria-invalid={error ? true : undefined}
                className="resize-none"
            />
            {error && <p className="text-sm font-medium text-danger-fg">{error}</p>}
            </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
            Cancelar
          </Button>
          <Button variant="danger" onClick={handleConfirm} disabled={submitting}>
            <X className="size-4" />
            {submitting ? 'Rechazando…' : 'Rechazar incidencia'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}