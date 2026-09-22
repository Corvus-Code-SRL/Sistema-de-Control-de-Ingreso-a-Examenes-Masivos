import { AlertCircle, RefreshCw } from 'lucide-react'
import type { ApiError } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

interface ErrorStateProps {
  error: ApiError
  onRetry?: () => void
  className?: string
}

/**
 * Fallo de una consulta, con el mensaje que envió el servidor.
 *
 * El mensaje del backend ya está redactado para el docente (par inactivo,
 * grupo inexistente), así que se muestra tal cual en lugar de uno genérico.
 */
export function ErrorState({ error, onRetry, className }: ErrorStateProps) {
  return (
    <div
      role="alert"
      className={cn('flex flex-col items-center gap-3 px-6 py-12 text-center', className)}
    >
      <span
        aria-hidden="true"
        className="flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-fg"
      >
        <AlertCircle className="size-6" />
      </span>

      <div className="space-y-1">
        <p className="sciem-h3">No se pudo cargar la información</p>
        <p className="mx-auto max-w-md text-sm text-muted-foreground">{error.message}</p>
      </div>

      {onRetry && (
        <Button variant="outline" size="sm" onClick={onRetry}>
          <RefreshCw className="size-4" />
          Reintentar
        </Button>
      )}
    </div>
  )
}
