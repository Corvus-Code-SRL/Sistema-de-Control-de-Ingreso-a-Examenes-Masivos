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

/** `request` envía la solicitud al docente; `direct` y `detect` anulan sin su confirmación. */
export type ConfirmMode = 'request' | 'direct' | 'detect'

interface RectifyConfirmDialogProps {
  record: RectifiableRecordDetail
  mode: ConfirmMode
  /** Motivo ya recortado que se va a confirmar; null mantiene el diálogo cerrado. */
  reason: string | null
  isSubmitting: boolean
  onConfirm: () => void
  onCancel: () => void
}

/**
 * Segundo paso: nombra al estudiante y al registro y explica qué va a pasar. Pedir la confirmación al docente
 * no retira el registro; anular sin su confirmación sí, y no se puede deshacer.
 */
export function RectifyConfirmDialog({
  record,
  mode,
  reason,
  isSubmitting,
  onConfirm,
  onCancel,
}: RectifyConfirmDialogProps) {
  const { estudiante, examen, tipo_falta: fault, rectificacion: summary } = record
  const teacher = summary.docente.nombre_completo ?? 'el docente que aprobó el reporte'
  const subject = `«${fault.nombre}» de ${estudiante.nombre_completo}, del examen «${examen.nombre}» (${formatPlainDate(examen.fecha)})`

  return (
    <Dialog open={reason !== null} onOpenChange={(open) => !open && !isSubmitting && onCancel()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>
            {mode === 'request' ? '¿Enviar la solicitud de rectificación?' : '¿Anular este registro?'}
          </DialogTitle>
          <DialogDescription>
            {mode === 'request' && (
              <>
                Se enviará a {teacher} la solicitud para retirar el registro {subject}. El registro seguirá activo
                en la Central de Riesgo hasta que el docente la acepte, y la solicitud vence en{' '}
                {summary.dias_vencimiento} días si no la responde.
              </>
            )}
            {mode === 'direct' && (
              <>
                {teacher} lleva más de {summary.dias_docente_inactivo} días sin ser docente activo. Se anulará el
                registro {subject} sin su confirmación: dejará de figurar en la Central y esta acción no se puede
                deshacer.
              </>
            )}
            {mode === 'detect' && (
              <>
                {teacher} ya no es docente activo, pero aún no se conoce desde cuándo. Al continuar se iniciará el
                plazo de {summary.dias_docente_inactivo} días de inactividad para anular el registro {subject} sin su
                confirmación; el registro no se anulará todavía.
              </>
            )}
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
          <Button
            type="button"
            variant={mode === 'request' ? 'default' : 'danger'}
            onClick={onConfirm}
            disabled={isSubmitting}
          >
            {isSubmitting
              ? 'Enviando…'
              : mode === 'request'
                ? 'Sí, enviar solicitud'
                : mode === 'direct'
                  ? 'Sí, anular registro'
                  : 'Sí, continuar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
