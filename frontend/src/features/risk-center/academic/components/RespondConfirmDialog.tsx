import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { RectificationRequestCard } from '../types/riskRectification.types'
import { formatPlainDate } from '../utils/format'
import type { RejectionDraft } from './RejectRequestForm'

/** Lo que el docente está por hacer: aceptar, o rechazar con lo que escribió y adjuntó. */
export type PendingResponse = { kind: 'accept' } | { kind: 'reject'; draft: RejectionDraft }

interface RespondConfirmDialogProps {
  request: RectificationRequestCard
  /** null mantiene el diálogo cerrado. */
  response: PendingResponse | null
  isSubmitting: boolean
  onConfirm: () => void
  onCancel: () => void
}

/** Segundo paso de la respuesta: nombra el registro y explica el efecto. Aceptar retira el registro de la Central. */
export function RespondConfirmDialog({ request, response, isSubmitting, onConfirm, onCancel }: RespondConfirmDialogProps) {
  const accepting = response?.kind === 'accept'
  const subject = `«${request.tipo_falta.nombre}» de ${request.estudiante.nombre_completo}, del examen «${request.examen.nombre}» (${formatPlainDate(request.examen.fecha)})`

  return (
    <Dialog open={response !== null} onOpenChange={(open) => !open && !isSubmitting && onCancel()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>{accepting ? '¿Aceptar la rectificación?' : '¿Rechazar la solicitud?'}</DialogTitle>
          <DialogDescription>
            {accepting
              ? `Se retirará de la Central de Riesgo el registro ${subject}. El registro dejará de figurar en la Central y esta acción no se puede deshacer.`
              : `El registro ${subject} seguirá en la Central de Riesgo y el administrador verá su motivo.`}
          </DialogDescription>
        </DialogHeader>

        {response?.kind === 'reject' && (
          <div className="space-y-1 rounded-md border border-border p-3 text-sm">
            <p className="text-xs font-semibold uppercase text-muted-foreground">Motivo del rechazo</p>
            <p className="whitespace-pre-line break-words">{response.draft.motivo}</p>
            <p className="text-xs text-muted-foreground">
              {response.draft.evidencia === null
                ? 'Sin imagen de respaldo.'
                : `Imagen adjunta: ${response.draft.evidencia.name}`}
            </p>
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button type="button" variant="danger" onClick={onConfirm} disabled={isSubmitting}>
            {isSubmitting ? 'Enviando…' : accepting ? 'Sí, aceptar' : 'Sí, rechazar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
