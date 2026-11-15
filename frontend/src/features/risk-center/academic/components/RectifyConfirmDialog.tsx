import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { RectificationFaultType } from '../types/riskRectification.types'
import type { PendingRectification } from './RectifyRecordForm'

interface RectifyConfirmDialogProps {
  /** Lo que se va a confirmar; null mantiene el diálogo cerrado. */
  pending: PendingRectification | null
  current: { faultTypeName: string; description: string | null }
  faultTypes: RectificationFaultType[]
  isSubmitting: boolean
  onConfirm: () => void
  onCancel: () => void
}

function Row({ label, before, after }: { label: string; before: string; after: string }) {
  return (
    <div className="grid gap-1 rounded-md border border-border p-3 text-sm sm:grid-cols-2">
      <p className="text-xs font-semibold uppercase text-muted-foreground sm:col-span-2">{label}</p>
      <p>
        <span className="block text-xs text-muted-foreground">Anterior</span>
        <span className="whitespace-pre-line break-words">{before}</span>
      </p>
      <p>
        <span className="block text-xs text-muted-foreground">Nuevo</span>
        <span className="whitespace-pre-line break-words font-medium">{after}</span>
      </p>
    </div>
  )
}

/** Segundo paso: se muestran los valores anteriores, los nuevos y el motivo antes de guardar. */
export function RectifyConfirmDialog({
  pending,
  current,
  faultTypes,
  isSubmitting,
  onConfirm,
  onCancel,
}: RectifyConfirmDialogProps) {
  const newTypeName =
    faultTypes.find((faultType) => faultType.id_falta === pending?.faultTypeId)?.nombre ?? 'Sin dato'

  return (
    <Dialog open={pending !== null} onOpenChange={(open) => !open && !isSubmitting && onCancel()}>
      <DialogContent className="sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>¿Confirmar la rectificación?</DialogTitle>
          <DialogDescription>
            El registro original no se borra: queda en el historial como «Anterior» y la nueva versión pasa a
            ser la «Vigente». La rectificación queda en la bitácora.
          </DialogDescription>
        </DialogHeader>

        {pending !== null && (
          <div className="space-y-3">
            <Row label="Tipo de falta" before={current.faultTypeName} after={newTypeName} />
            <Row
              label="Descripción"
              before={current.description ?? 'Sin descripción'}
              after={pending.description ?? 'Sin descripción'}
            />
            <div className="rounded-md border border-border p-3 text-sm">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Motivo</p>
              <p className="whitespace-pre-line break-words">{pending.reason}</p>
            </div>
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button type="button" onClick={onConfirm} disabled={isSubmitting}>
            {isSubmitting ? 'Guardando…' : 'Confirmar rectificación'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
