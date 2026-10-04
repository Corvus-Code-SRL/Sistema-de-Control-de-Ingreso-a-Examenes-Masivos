import { ShieldAlert } from 'lucide-react'
import { cn } from '@/lib/utils'

interface PermissionDeniedProps {
  message?: string
  className?: string
}

/**
 * Aviso de que el rol de la sesión no puede ver este recurso.
 *
 * Se muestra en el lugar del contenido: un 403 no es una sesión caída, así que no cierra la
 * sesión ni lleva al login.
 */
export function PermissionDenied({
  message = 'Su rol no tiene permiso para ver esta información.',
  className,
}: PermissionDeniedProps) {
  return (
    <div
      role="alert"
      className={cn('flex flex-col items-center gap-3 px-6 py-12 text-center', className)}
    >
      <span
        aria-hidden="true"
        className="flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-fg"
      >
        <ShieldAlert className="size-6" />
      </span>

      <div className="space-y-1">
        <p className="sciem-h3">Sin permiso</p>
        <p className="mx-auto max-w-md text-sm text-muted-foreground">{message}</p>
      </div>
    </div>
  )
}
