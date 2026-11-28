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

function DialogDangerIcon() {
  return (
    <span
      style={{
        width: 40,
        height: 40,
        backgroundColor: 'var(--danger-soft, #FDE2E1)',
        color: 'var(--danger, #D92D20)',
      }}
      className="flex shrink-0 items-center justify-center rounded-full"
    >
      <svg
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="10" />
        <path d="m15 9-6 6" />
        <path d="m9 9 6 6" />
      </svg>
    </span>
  )
}

function ConfirmButtonIcon() {
  return (
    <svg
      width="17"
      height="17"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="10" />
      <path d="m15 9-6 6" />
      <path d="m9 9 6 6" />
    </svg>
  )
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
              <DialogDangerIcon />
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
              <p role="alert" className="text-sm font-medium text-[color:var(--danger-fg)]">
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
                <ConfirmButtonIcon />
                {submitting ? 'Desvinculando…' : 'Desvincular'}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
