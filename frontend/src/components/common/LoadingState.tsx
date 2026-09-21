import { Skeleton } from '@/components/ui/skeleton'

interface LoadingStateProps {
  /** Filas de esqueleto a dibujar; conviene que coincidan con el tamaño de página. */
  rows?: number
  label?: string
}

/**
 * Espera de una consulta.
 *
 * Se anuncia con `role="status"` para que un lector de pantalla informe de la
 * carga: el esqueleto por sí solo no dice nada.
 */
export function LoadingState({ rows = 5, label = 'Cargando información' }: LoadingStateProps) {
  return (
    <div role="status" aria-live="polite" className="space-y-3 p-4">
      <span className="sr-only">{label}</span>

      {Array.from({ length: rows }, (_, index) => (
        <Skeleton key={index} className="h-12 w-full" aria-hidden="true" />
      ))}
    </div>
  )
}
