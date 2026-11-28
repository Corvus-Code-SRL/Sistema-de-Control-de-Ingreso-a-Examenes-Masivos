import { RotateCcw } from 'lucide-react'
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

interface ReactivateSubjectDialogProps {
  subject: UnassignableSubject | null
  careerName: string
  periodLabel: string
  submitting: boolean
  /** Error de red o del servidor (no de regla de negocio), con el que se puede reintentar. */
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

/** Confirmación sencilla antes de reactivar. Cancelar cierra sin pedir nada al servidor. */
export function ReactivateSubjectDialog({
  subject,
  careerName,
  periodLabel,
  submitting,
  error,
  onCancel,
  onConfirm,
}: ReactivateSubjectDialogProps) {
  return (
    <Dialog open={subject !== null} onOpenChange={(open) => !open && !submitting && onCancel()}>
      <DialogContent showCloseButton={false} className="sm:max-w-lg">
        {subject && (
          <>
            <DialogHeader className="flex-row items-start gap-3 space-y-0 text-left">
              <span
                aria-hidden="true"
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-deep"
              >
                <RotateCcw className="size-[18px]" />
              </span>
              <div className="space-y-1">
                <DialogTitle className="leading-snug">¿Reactivar {subject.nombre}?</DialogTitle>
                <DialogDescription>
                  {subject.codigo} · {careerName} · período {periodLabel}
                </DialogDescription>
              </div>
            </DialogHeader>

            <ul className="list-disc space-y-1.5 pl-5 text-sm text-muted-foreground">
              <li>Volverá a aparecer al registrar grupos de esta carrera en {periodLabel}.</li>
            </ul>

            {error && (
              <p role="alert" className="text-sm font-medium text-danger-fg">
                {error}
              </p>
            )}

            <DialogFooter className="gap-2 sm:justify-end">
              <Button type="button" variant="outline" disabled={submitting} onClick={onCancel}>
                Cancelar
              </Button>
              <Button type="button" disabled={submitting} onClick={onConfirm}>
                {submitting ? 'Reactivando…' : 'Reactivar'}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
