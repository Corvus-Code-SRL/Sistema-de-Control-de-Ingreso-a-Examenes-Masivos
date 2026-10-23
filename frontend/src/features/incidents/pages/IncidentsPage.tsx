import { useCallback, useMemo, useState } from 'react'
import { AlertTriangle, Inbox } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { Toast } from '@/components/ui/toast'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { IncidentsList } from '../components/IncidentsList'
import { IncidentDetailPanel } from '../components/IncidentDetailPanel'
import { listIncidents } from '../services/incidentsService'
import type { IncidentFilter, IncidentReview } from '../types/incident.types'

interface TabDefinition {
  value: IncidentFilter
  label: string
}

const TABS: TabDefinition[] = [
  { value: 'pendiente', label: 'Pendientes' },
  { value: 'revisada', label: 'Revisadas' },
]

type Orden = 'asc' | 'desc'

interface ToastState {
  title: string
  description: string
}

export function IncidentsPage() {
  const [filter, setFilter] = useState<IncidentFilter>('pendiente')
  const [orden, setOrden] = useState<Orden>('asc')
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [toast, setToast] = useState<ToastState | null>(null)

  const resource = useAsyncResource(
    useCallback((signal) => listIncidents(filter, undefined, orden, signal), [filter, orden]),
    [filter, orden]
  )

  const incidents = resource.data?.data ?? []
  const totalPendientes = resource.data?.meta.total_pendientes ?? 0
  const totalRevisadas = resource.data?.meta.total_revisadas ?? 0

  function handleFilterChange(next: IncidentFilter) {
    setFilter(next)
    setSelectedId(null)
    setOrden(next === 'pendiente' ? 'asc' : 'desc')
  }

  function handleReviewed(message: ToastState) {
    setSelectedId(null)
    setToast(message)
    resource.reload()
  }

  const selectedIncident = useMemo<IncidentReview | null>(
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

      <div className="flex flex-wrap items-end justify-between gap-3">
        <Tabs
          filter={filter}
          onChange={handleFilterChange}
          totalPendientes={totalPendientes}
          totalRevisadas={totalRevisadas}
        />

        <div className="flex items-center gap-2">
          <label htmlFor="orden" className="text-sm text-muted-foreground">
            Ordenar por
          </label>
          <Select value={orden} onValueChange={(value) => setOrden(value as Orden)}>
            <SelectTrigger id="orden" className="h-9 w-[200px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="asc">Más antiguas primero</SelectItem>
              <SelectItem value="desc">Más recientes primero</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

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
        <>
          {/* Móvil: el detalle reemplaza la lista */}
          <div className="lg:hidden">
            {selectedIncident ? (
              <IncidentDetailPanel
                incidentId={selectedIncident.id}
                onReviewed={handleReviewed}
                onBack={() => setSelectedId(null)}
              />
            ) : (
              <IncidentsList
                incidents={incidents}
                selectedId={selectedId}
                onSelect={setSelectedId}
                filter={filter}
              />
            )}
          </div>

          {/* Escritorio: lista + detalle */}
          <div className="hidden gap-5 lg:grid lg:grid-cols-[420px_1fr]">
            <IncidentsList
              incidents={incidents}
              selectedId={selectedId}
              onSelect={setSelectedId}
              filter={filter}
            />

            {selectedIncident ? (
              <IncidentDetailPanel
                incidentId={selectedIncident.id}
                onReviewed={handleReviewed}
              />
            ) : (
              <Card className="flex w-full items-center justify-center p-12">
                <p className="text-sm text-muted-foreground">
                  Seleccione una incidencia para ver el detalle.
                </p>
              </Card>
            )}
          </div>
        </>
      )}

      {toast && (
        <Toast
          title={toast.title}
          description={toast.description}
          onClose={() => setToast(null)}
        />
      )}
    </AppShell>
  )
}

/* ========================================================================= */

interface TabsProps {
  filter: IncidentFilter
  onChange: (value: IncidentFilter) => void
  totalPendientes: number
  totalRevisadas: number
}

function Tabs({ filter, onChange, totalPendientes, totalRevisadas }: TabsProps) {
  return (
    <div role="tablist" className="flex gap-1 border-b border-border">
      {TABS.map((tab) => {
        const isActive = tab.value === filter
        const count = tab.value === 'pendiente' ? totalPendientes : totalRevisadas

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
                  isActive ? 'bg-brand-soft text-brand-deep' : 'bg-neutral-soft text-neutral-fg'
                }`}
              >
                {count}
              </span>
            )}
            {isActive && (
              <span aria-hidden="true" className="absolute inset-x-0 -bottom-px h-0.5 bg-brand" />
            )}
          </button>
        )
      })}
    </div>
  )
}
