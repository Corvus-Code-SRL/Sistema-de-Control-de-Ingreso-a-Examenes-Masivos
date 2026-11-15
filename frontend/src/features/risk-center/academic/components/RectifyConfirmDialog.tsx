import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { RectifiableRecordDetail } from '../types/riskRectification.types'
import { formatPlainDate } from '../utils/format'

interface RectifyConfirmDialogProps {
  record: RectifiableRecordDetail
  /** Motivo ya recortado que se va a confirmar; null mantiene el diálogo cerrado. */
  reason: string | null
  isSubmitting: boolean
  onConfirm: () => void
  onCancel: () => void
}

/** Segundo paso: nombra al estudiante y al registro y avisa que el retiro de la Central no se puede deshacer. */
export function RectifyConfirmDialog({ record, reason, isSubmitting, onConfirm, onCancel }: RectifyConfirmDialogProps) {
  return (
    <Dialog open={reason !== null} onOpenChange={(open) => !open && !isSubmitting && onCancel()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>¿Rectificar este registro?</DialogTitle>
          <DialogDescription>
            Se retirará de la Central de Riesgo el registro «{record.tipo_falta.nombre}» de{' '}
            {record.estudiante.nombre_completo}, del examen «{record.examen.nombre}» (
            {formatPlainDate(record.examen.fecha)}). El registro dejará de figurar en la Central y esta acción no
            se puede deshacer.
          </DialogDescription>
        </DialogHeader>

        {reason !== null && (
          <div className="rounded-md border border-border p-3 text-sm">
            <p className="text-xs font-semibold uppercase text-muted-foreground">Motivo</p>
            <p className="whitespace-pre-line break-words">{reason}</p>
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button type="button" variant="danger" onClick={onConfirm} disabled={isSubmitting}>
            {isSubmitting ? 'Rectificando…' : 'Sí, rectificar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
