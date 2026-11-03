import { FilterX, ScrollText, SearchX } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { Button } from '@/components/ui/button'

interface NoMatchesStateProps {
  onClear: () => void
}

/** Los filtros no encontraron nada: se explica y se ofrece quitarlos. */
export function AuditLogNoMatches({ onClear }: NoMatchesStateProps) {
  return (
    <EmptyState
      icon={SearchX}
      title="No hay operaciones que coincidan"
      description="Pruebe con otro usuario, otra acción u otro rango de fechas."
      action={
        <Button variant="outline" onClick={onClear}>
          <FilterX className="size-4" aria-hidden="true" />
          Limpiar filtros
        </Button>
      }
    />
  )
}

/** La bitácora todavía no tiene ninguna operación: no es un error ni hay filtros que limpiar. */
export function AuditLogEmpty() {
  return (
    <EmptyState
      icon={ScrollText}
      title="Aún no hay operaciones registradas"
      description="Las operaciones del sistema aparecerán aquí a medida que se realicen."
    />
  )
}
