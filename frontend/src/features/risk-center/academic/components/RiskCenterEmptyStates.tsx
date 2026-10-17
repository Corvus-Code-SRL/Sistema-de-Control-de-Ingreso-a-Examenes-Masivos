import { ClipboardList, FilterX, SearchX } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { Button } from '@/components/ui/button'

interface NoMatchesStateProps {
  onClear: () => void
}

/** La búsqueda o el filtro no encontraron nada: se explica y se ofrece quitar los filtros. */
export function RiskCenterNoMatches({ onClear }: NoMatchesStateProps) {
  return (
    <EmptyState
      icon={SearchX}
      title="No hay registros que coincidan"
      description="Pruebe con otro nombre o código, o quite el filtro de tipo."
      action={
        <Button variant="outline" onClick={onClear}>
          <FilterX className="size-4" aria-hidden="true" />
          Limpiar filtros
        </Button>
      }
    />
  )
}

/** La Central de Riesgo todavía no tiene ningún registro: no es un error ni hay filtros que limpiar. */
export function RiskCenterEmptyRegistry() {
  return (
    <EmptyState
      icon={ClipboardList}
      title="Aún no hay registros en la Central de Riesgo"
      description="Los reportes aprobados aparecerán aquí cuando se confirmen."
    />
  )
}
