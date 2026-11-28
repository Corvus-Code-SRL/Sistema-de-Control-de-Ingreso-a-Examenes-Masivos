import { XCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { UnassignableSubject } from '../types/unassignSubject.types'

interface UnassignSubjectDialogProps {
  subject: UnassignableSubject | null
  careerName: string
  periodLabel: string
  submitting: boolean
  /** Error de red o del servidor (no de regla de negocio), con el que se puede reintentar. */
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

/** CA-3: confirmación antes de desvincular. Cancelar cierra sin pedir nada al servidor. */
export function UnassignSubjectDialog({
  subject,
  careerName,
  periodLabel,
  submitting,
  error,
  onCancel,
  onConfirm,
}: UnassignSubjectDialogProps) {
  return (
    <Dialog
      open={subject !== null}
      onOpenChange={(open) => !open && !submitting && onCancel()}
    >
      <DialogContent showCloseButton={false} className="sm:max-w-lg">
        {subject && (
          <>
            <DialogHeader className="flex-row items-start gap-3 space-y-0 text-left">
              <span
                aria-hidden="true"
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-danger-soft text-danger"
              >
                <XCircle className="size-[18px]" />
              </span>
              <div className="space-y-1">
                <DialogTitle className="leading-snug">
                  ¿Desvincular {subject.nombre}?
                </DialogTitle>
                <DialogDescription>
                  {subject.codigo} · {careerName} · período {periodLabel}
                </DialogDescription>
              </div>
            </DialogHeader>

            <ul className="list-disc space-y-1.5 pl-5 text-sm text-muted-foreground">
              <li>Ya no aparecerá al registrar grupos de esta carrera en {periodLabel}.</li>
              <li>No se borra: queda «Desvinculada» y el cambio va a la bitácora.</li>
              <li>Los períodos anteriores no cambian.</li>
            </ul>

            {error && (
              <p role="alert" className="text-sm font-medium text-danger-fg">
                {error}
              </p>
            )}

            <DialogFooter className="gap-2 sm:justify-end">
              <Button
                type="button"
                variant="outline"
                disabled={submitting}
                onClick={onCancel}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                variant="danger"
                disabled={submitting}
                onClick={onConfirm}
              >
                <XCircle className="size-[17px]" />
                {submitting ? 'Desvinculando…' : 'Desvincular'}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
