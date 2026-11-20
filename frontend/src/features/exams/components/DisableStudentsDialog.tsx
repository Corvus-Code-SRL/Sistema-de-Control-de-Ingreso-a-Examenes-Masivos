import { useEffect, useRef, useState } from 'react'
import { AlertTriangle, UserX, XCircle } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { FormSelect } from '@/components/common/FormSelect'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

import type { NoEligibleReason } from '../types/eligibleList.types'
import { NO_ELIGIBLE_REASONS } from '../types/eligibleList.types'
import { validateOtroDetalle } from '../utils/eligibleListUtils'

interface DisableStudentsDialogProps {
  open: boolean
  studentCount: number
  onClose: () => void
  onApply: (motivo: NoEligibleReason, detalle?: string) => void
}

const reasonOptions = (Object.keys(NO_ELIGIBLE_REASONS) as NoEligibleReason[]).map((key) => ({
  value: key,
  label: NO_ELIGIBLE_REASONS[key],
}))

export function DisableStudentsDialog({
  open,
  studentCount,
  onClose,
  onApply,
}: DisableStudentsDialogProps) {
  const [motivo, setMotivo] = useState<NoEligibleReason | ''>('')
  const [detalle, setDetalle] = useState('')
  const [error, setError] = useState<string | null>(null)
  const selectRef = useRef<HTMLButtonElement | null>(null)

  useEffect(() => {
    if (open) {
      setMotivo('')
      setDetalle('')
      setError(null)
      setTimeout(() => selectRef.current?.focus(), 100)
    }
  }, [open])

  const handleApply = (): void => {
    if (!motivo) {
      setError('Debe seleccionar un motivo.')
      return
    }
    if (motivo === 'OTRO') {
      const detailError = validateOtroDetalle(detalle)
      if (detailError) {
        setError(detailError)
        return
      }
    }
    onApply(motivo, motivo === 'OTRO' ? detalle : undefined)
  }

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {/* #6: ícono dentro de círculo */}
            <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger-fg">
              <UserX aria-hidden="true" className="size-4" />
            </span>
            No habilitar a {studentCount} estudiantes
          </DialogTitle>
          <DialogDescription>
            Indique el motivo por el cual estos estudiantes no podrán rendir el examen.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="motivo">
              Motivo <span className="text-danger">*</span>
            </Label>
            <div ref={selectRef as unknown as React.RefObject<HTMLDivElement>}>
              <FormSelect
                id="motivo"
                value={motivo}
                placeholder="Seleccione un motivo"
                options={reasonOptions}
                onValueChange={(v) => { setMotivo(v as NoEligibleReason); setError(null) }}
                ariaLabel="Motivo"
                invalid={!motivo && !!error}
              />
            </div>
          </div>

          {motivo === 'OTRO' && (
            <div className="space-y-2">
              <Label htmlFor="detalle">
                Detalle <span className="text-danger">*</span>
              </Label>
              <Textarea
                id="detalle"
                value={detalle}
                onChange={(e) => { setDetalle(e.target.value); setError(null) }}
                placeholder="Describa el motivo (mínimo 10 caracteres, máximo 500)"
                maxLength={500}
                aria-invalid={!!error}
                className="resize-none"
              />
              <p className="text-xs text-muted-foreground">{detalle.length} / 500</p>
            </div>
          )}

          {error && (
            <Alert variant="destructive">
              <AlertTriangle aria-hidden="true" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          {/* #6: mismo ícono en el botón de deshabilitar */}
          <Button variant="destructive" onClick={handleApply}>
            <XCircle aria-hidden="true" className="size-4" />
            No habilitar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
