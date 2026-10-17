import { useCallback, useMemo, useState } from 'react'
import { AlertTriangle, Inbox } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { IncidentsList } from '../components/IncidentsList'
import { IncidentDetailPanel } from '../components/IncidentDetailPanel'
import { listIncidents } from '../services/incidentsService'
import type { IncidentFilter, IncidentReport } from '../types/incident.types'

interface TabDefinition {
  value: IncidentFilter
  label: string
}

const TABS: TabDefinition[] = [
  { value: 'pendiente', label: 'Pendientes' },
  { value: 'revisada', label: 'Revisadas' },
]

export function IncidentsPage() {
  const [filter, setFilter] = useState<IncidentFilter>('pendiente')
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const resource = useAsyncResource(
    useCallback((signal) => listIncidents(filter, undefined, signal), [filter]),
    [filter]
  )

  const incidents = resource.data?.data ?? []
  const totalPendientes = resource.data?.meta.total_pendientes ?? 0
  const totalRevisadas  = resource.data?.meta.total_revisadas ?? 0

  function handleFilterChange(next: IncidentFilter) {
    setFilter(next)
    setSelectedId(null)
  }

  const selectedIncident = useMemo<IncidentReport | null>(
    () => incidents.find((i) => i.id === selectedId) ?? null,
    [incidents, selectedId]
  )

  const isEmpty = resource.status === 'success' && incidents.length === 0
  const isPendiente = filter === 'pendiente'

  return (
    <AppShell
      mobileTitle="Incidencias"
      breadcrumbs={[{ label: 'Exámenes' }, { label: 'Incidencias' }]}
    >
      <PageHeader
        title="Incidencias"
        subtitle="Reportes de sus exámenes que esperan su revisión. Solo lo aprobado llega a la Central de Riesgo."
        actions={
          totalPendientes > 0 && (
            <span className="inline-flex items-center gap-1.5 rounded-md bg-warn-soft px-2.5 py-1 text-xs font-medium text-warn-fg">
              <AlertTriangle className="size-3.5" aria-hidden="true" />
              {totalPendientes} {totalPendientes === 1 ? 'pendiente' : 'pendientes'}
            </span>
          )
        }
      />

      <Tabs
        filter={filter}
        onChange={handleFilterChange}
        totalPendientes={totalPendientes}
        totalRevisadas={totalRevisadas}          // NUEVO
      />
      {resource.status === 'loading' && (
        <Card className="p-0">
          <LoadingState rows={4} label="Cargando incidencias" />
        </Card>
      )}

      {resource.status === 'error' && resource.error && (
        <Card className="p-0">
          <ErrorState error={resource.error} onRetry={resource.reload} />
        </Card>
      )}

      {isEmpty && (
        <Card className="p-0">
          <EmptyState
            icon={isPendiente ? AlertTriangle : Inbox}
            title={
              isPendiente
                ? 'No hay incidencias pendientes de revisión'
                : 'No hay incidencias revisadas'
            }
            description={
              isPendiente
                ? 'Cuando un controlador reporte un intento en uno de sus exámenes, aparecerá aquí.'
                : 'Las incidencias aprobadas o rechazadas aparecerán aquí.'
            }
          />
        </Card>
      )}

      {!isEmpty && resource.status === 'success' && (
        <div className="grid gap-5 lg:grid-cols-[420px_1fr]">
          <IncidentsList
            incidents={incidents}
            selectedId={selectedId}
            onSelect={setSelectedId}
            filter={filter}
          />

          {selectedIncident && (
          <>
            <div className="hidden lg:block">
              <IncidentDetailPanel
                incidentId={selectedIncident.id}
                onReviewed={resource.reload}
              />
            </div>
            <div className="lg:hidden">
              <IncidentDetailPanel
                incidentId={selectedIncident.id}
                onReviewed={() => {
                  setSelectedId(null)
                  resource.reload()
                }}
                onBack={() => setSelectedId(null)}
              />
            </div>
          </>
        )}

          {!selectedIncident && (
            <div className="hidden lg:flex">
              <Card className="flex w-full items-center justify-center p-12">
                <p className="text-sm text-muted-foreground">
                  Seleccione una incidencia para ver el detalle.
                </p>
              </Card>
            </div>
          )}
        </div>
      )}
    </AppShell>
  )
}

/* ========================================================================= */

interface TabsProps {
  filter: IncidentFilter
  onChange: (value: IncidentFilter) => void
  totalPendientes: number
  totalRevisadas: number          // NUEVO
}

function Tabs({ filter, onChange, totalPendientes, totalRevisadas }: TabsProps) {
  return (
    <div role="tablist" className="flex gap-1 overflow-x-auto border-b border-border">
      {TABS.map((tab) => {
        const isActive = tab.value === filter
        const count = tab.value === 'pendiente' ? totalPendientes : totalRevisadas   // NUEVO

        return (
          <button
            key={tab.value}
            role="tab"
            type="button"
            aria-selected={isActive}
            onClick={() => onChange(tab.value)}
            className={`relative flex h-10 shrink-0 items-center gap-2 px-3.5 text-sm font-medium transition-colors ${
              isActive ? 'text-brand' : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            {tab.label}
            {count > 0 && (
              <span
                className={`rounded-full px-1.5 text-xs font-semibold ${
                  isActive
                    ? 'bg-brand-soft text-brand-deep'
                    : 'bg-neutral-soft text-neutral-fg'
                }`}
              >
                {count}
              </span>
            )}
            {isActive && (
              <span
                aria-hidden="true"
                className="absolute inset-x-0 -bottom-px h-0.5 bg-brand"
              />
            )}
          </button>
        )
      })}
    </div>
  )
}