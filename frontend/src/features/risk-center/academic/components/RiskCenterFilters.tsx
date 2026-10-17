import { Search } from 'lucide-react'
import { FormSelect } from '@/components/common/FormSelect'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { FaultType } from '../types/riskCenter.types'
import { MAX_SEARCH_LENGTH } from '../utils/filters'

const ALL_TYPES = 'todos'

interface RiskCenterFiltersProps {
  searchInput: string
  onSearchChange: (value: string) => void
  /** Tipo elegido (`?tipo=`), o null para todos. */
  type: number | null
  faultTypes: FaultType[]
  /** El catálogo de tipos no cargó: el filtro se deshabilita, el listado sigue funcionando. */
  typesUnavailable: boolean
  onTypeChange: (type: number | null) => void
}

/** Búsqueda por nombre o código SIS y filtro por tipo de falta del listado. */
export function RiskCenterFilters({
  searchInput,
  onSearchChange,
  type,
  faultTypes,
  typesUnavailable,
  onTypeChange,
}: RiskCenterFiltersProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row">
      <div className="relative sm:w-96">
        <Search
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          type="search"
          value={searchInput}
          maxLength={MAX_SEARCH_LENGTH}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder="Buscar por nombre o código SIS"
          aria-label="Buscar por nombre o código SIS"
          className="h-11 pl-8"
        />
      </div>

      <div className="sm:w-64">
        <Label htmlFor="risk-center-type" className="sr-only">
          Tipo de falta
        </Label>
        <FormSelect
          id="risk-center-type"
          value={type === null ? ALL_TYPES : String(type)}
          placeholder={typesUnavailable ? 'Tipos no disponibles' : 'Todos los tipos'}
          disabled={typesUnavailable}
          options={[
            { value: ALL_TYPES, label: 'Todos los tipos' },
            ...faultTypes.map((faultType) => ({
              value: String(faultType.id_falta),
              label: faultType.nombre,
            })),
          ]}
          onValueChange={(value) => onTypeChange(value === ALL_TYPES ? null : Number(value))}
        />
      </div>
    </div>
  )
}
