import { AlertCircle, Search } from 'lucide-react'
import { FormSelect } from '@/components/common/FormSelect'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { AuditLogOptions } from '../types/auditLog.types'
import { MAX_SEARCH_LENGTH } from '../utils/filters'

const ALL = 'todos'

interface AuditLogFiltersProps {
  searchInput: string
  onSearchChange: (value: string) => void
  accion: string | null
  modulo: string | null
  desde: string | null
  hasta: string | null
  options: AuditLogOptions
  /** Los catálogos no cargaron: los selectores se deshabilitan, el listado sigue funcionando. */
  optionsUnavailable: boolean
  onActionChange: (accion: string | null) => void
  onModuleChange: (modulo: string | null) => void
  onSinceChange: (desde: string | null) => void
  onUntilChange: (hasta: string | null) => void
  /** Mensaje cuando «Desde» es posterior a «Hasta»; null si el rango es válido. */
  rangeError: string | null
}

/** Usuario, acción, módulo y rango de fechas (día de La Paz) del listado. */
export function AuditLogFilters({
  searchInput,
  onSearchChange,
  accion,
  modulo,
  desde,
  hasta,
  options,
  optionsUnavailable,
  onActionChange,
  onModuleChange,
  onSinceChange,
  onUntilChange,
  rangeError,
}: AuditLogFiltersProps) {
  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
        <div className="relative">
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
            aria-label="Buscar usuario por nombre o código SIS"
            className="h-11 pl-8"
          />
        </div>

        <div>
          <Label htmlFor="audit-log-action" className="sr-only">
            Acción
          </Label>
          <FormSelect
            id="audit-log-action"
            ariaLabel="Acción"
            value={accion ?? ALL}
            placeholder={optionsUnavailable ? 'Acciones no disponibles' : 'Todas las acciones'}
            disabled={optionsUnavailable}
            options={[
              { value: ALL, label: 'Todas las acciones' },
              ...options.acciones.map((action) => ({ value: action.codigo, label: action.etiqueta })),
            ]}
            onValueChange={(value) => onActionChange(value === ALL ? null : value)}
          />
        </div>

        <div>
          <Label htmlFor="audit-log-module" className="sr-only">
            Módulo
          </Label>
          <FormSelect
            id="audit-log-module"
            ariaLabel="Módulo"
            value={modulo ?? ALL}
            placeholder={optionsUnavailable ? 'Módulos no disponibles' : 'Todos los módulos'}
            disabled={optionsUnavailable}
            options={[
              { value: ALL, label: 'Todos los módulos' },
              ...options.modulos.map((module) => ({ value: module.clave, label: module.etiqueta })),
            ]}
            onValueChange={(value) => onModuleChange(value === ALL ? null : value)}
          />
        </div>

        <div className="space-y-1">
          <Label htmlFor="audit-log-since" className="text-xs text-muted-foreground">
            Desde
          </Label>
          <Input
            id="audit-log-since"
            type="date"
            value={desde ?? ''}
            aria-invalid={rangeError !== null || undefined}
            aria-describedby={rangeError ? 'audit-log-range-error' : undefined}
            onChange={(event) => onSinceChange(event.target.value || null)}
            className="h-11"
          />
        </div>

        <div className="space-y-1">
          <Label htmlFor="audit-log-until" className="text-xs text-muted-foreground">
            Hasta
          </Label>
          <Input
            id="audit-log-until"
            type="date"
            value={hasta ?? ''}
            aria-invalid={rangeError !== null || undefined}
            aria-describedby={rangeError ? 'audit-log-range-error' : undefined}
            onChange={(event) => onUntilChange(event.target.value || null)}
            className="h-11"
          />
        </div>
      </div>

      {rangeError && (
        <p id="audit-log-range-error" role="alert" className="flex items-center gap-1.5 text-sm text-danger-fg">
          <AlertCircle className="size-4 shrink-0" aria-hidden="true" />
          {rangeError}
        </p>
      )}
    </div>
  )
}
