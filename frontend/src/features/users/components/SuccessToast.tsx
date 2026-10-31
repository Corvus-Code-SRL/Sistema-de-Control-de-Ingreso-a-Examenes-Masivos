import { useEffect } from 'react'
import { CheckCircle, X } from 'lucide-react'
import { Button } from '@/components/ui/button'

export interface ToastMessage {
  title: string
  description: string
}

interface SuccessToastProps {
  message: ToastMessage | null
  onDismiss: () => void
}

const VISIBLE_MS = 5000

/**
 * Confirmación de una operación terminada.
 *
 * Fija a la ventana y no al contenido, para que no se desplace al hacer scroll.
 */
export function SuccessToast({ message, onDismiss }: SuccessToastProps) {
  useEffect(() => {
    if (!message) return

    const timer = window.setTimeout(onDismiss, VISIBLE_MS)

    return () => window.clearTimeout(timer)
  }, [message, onDismiss])

  if (!message) return null

  return (
    <div
      aria-live="polite"
      className="fixed top-20 right-4 z-40 flex w-[380px] max-w-[calc(100vw-2rem)] items-start gap-3 rounded-lg border border-l-4 border-border-soft border-l-ok bg-surface p-3.5 shadow-[var(--shadow-lg)] animate-in fade-in slide-in-from-top-4 duration-300"
    >
      <CheckCircle aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-ok" />
      <div className="flex grow flex-col">
        <span className="text-sm font-semibold text-foreground">{message.title}</span>
        <span className="mt-0.5 text-sm text-muted-foreground">{message.description}</span>
      </div>
      <Button variant="ghost" size="icon-xs" onClick={onDismiss} aria-label="Cerrar aviso">
        <X />
      </Button>
    </div>
  )
}
