import { AlertCircle, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FormSelect } from '@/components/common/FormSelect'

import type { AuditLogOptions } from '@/features/audit-log/types/auditLog.types'
import type { AuditLogReportExportFilters } from '../types/auditLogReport.types'

const ALL = 'todos'

interface AuditLogReportFiltersProps {
  draft: AuditLogReportExportFilters
  options: AuditLogOptions
  optionsUnavailable: boolean
  rangeError: string | null
  generating: boolean
  onChange: (draft: AuditLogReportExportFilters) => void
  onGenerate: () => void
  onClear: () => void
}

/** HU-068: draft filters applied explicitly by the administrator. */
export function AuditLogReportFilters({
  draft,
  options,
  optionsUnavailable,
  rangeError,
  generating,
  onChange,
  onGenerate,
  onClear,
}: AuditLogReportFiltersProps) {
  return (
    <form
      className="space-y-3"
      noValidate
      onSubmit={(event) => {
        event.preventDefault()

        if (!rangeError && !generating) {
          onGenerate()
        }
      }}
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[auto_auto_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)]">

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="audit-report-desde">
            Desde *
          </Label>

          <Input
            id="audit-report-desde"
            type="date"
            required
            value={draft.desde}
            max={draft.hasta || undefined}
            aria-invalid={rangeError !== null}
            aria-describedby={
              rangeError ? 'audit-report-range-error' : undefined
            }
            onChange={(event) =>
              onChange({
                ...draft,
                desde: event.target.value,
              })
            }
            className="h-10 w-full"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="audit-report-hasta">
            Hasta *
          </Label>

          <Input
            id="audit-report-hasta"
            type="date"
            required
            value={draft.hasta}
            min={draft.desde || undefined}
            aria-invalid={rangeError !== null}
            aria-describedby={
              rangeError ? 'audit-report-range-error' : undefined
            }
            onChange={(event) =>
              onChange({
                ...draft,
                hasta: event.target.value,
              })
            }
            className="h-10 w-full"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="audit-report-user">
            Usuario
          </Label>

          <div className="relative">
            <Search
              aria-hidden="true"
              className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />

            <Input
              id="audit-report-user"
              type="search"
              value={draft.q}
              maxLength={100}
              placeholder="Buscar por nombre o código SIS"
              onChange={(event) =>
                onChange({
                  ...draft,
                  q: event.target.value,
                })
              }
              className="h-10 pl-8"
            />
          </div>
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="audit-report-action">
            Acción
          </Label>

          <FormSelect
            id="audit-report-action"
            ariaLabel="Acción"
            className="!h-10"
            value={draft.accion ?? ALL}
            placeholder={
              optionsUnavailable
                ? 'Acciones no disponibles'
                : 'Todas las acciones'
            }
            disabled={optionsUnavailable}
            options={[
              {
                value: ALL,
                label: 'Todas las acciones',
              },
              ...options.acciones.map((action) => ({
                value: action.codigo,
                label: action.etiqueta,
              })),
            ]}
            onValueChange={(value) =>
              onChange({
                ...draft,
                accion: value === ALL ? null : value,
              })
            }
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="audit-report-module">
            Módulo
          </Label>

          <FormSelect
            id="audit-report-module"
            ariaLabel="Módulo"
            className="!h-10"
            value={draft.modulo ?? ALL}
            placeholder={
              optionsUnavailable
                ? 'Módulos no disponibles'
                : 'Todos los módulos'
            }
            disabled={optionsUnavailable}
            options={[
              {
                value: ALL,
                label: 'Todos los módulos',
              },
              ...options.modulos.map((module) => ({
                value: module.clave,
                label: module.etiqueta,
              })),
            ]}
            onValueChange={(value) =>
              onChange({
                ...draft,
                modulo: value === ALL ? null : value,
              })
            }
          />
        </div>
      </div>

      {rangeError && (
        <p
          id="audit-report-range-error"
          role="alert"
          className="flex items-center gap-1.5 text-sm text-danger-fg"
        >
          <AlertCircle
            className="size-4 shrink-0"
            aria-hidden="true"
          />
          {rangeError}
        </p>
      )}

      {optionsUnavailable && (
        <p
          role="status"
          className="text-sm text-muted-foreground"
        >
          No se pudieron cargar las acciones y los módulos.
          Puede generar el reporte con los demás filtros.
        </p>
      )}

      <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
        <Button
          type="button"
          variant="outline"
          onClick={onClear}
          disabled={generating}
          className="h-10"
        >
          Limpiar filtros
        </Button>

        <Button
          type="submit"
          disabled={rangeError !== null || generating}
          className="h-10"
        >
          {generating ? 'Generando...' : 'Generar reporte'}
        </Button>
      </div>
    </form>
  )
}