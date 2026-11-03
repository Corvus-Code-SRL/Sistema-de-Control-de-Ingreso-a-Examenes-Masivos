import { useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataPagination } from '@/components/common/DataPagination'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { AuditLogCardList } from '../components/AuditLogCardList'
import { AuditLogDetailPanel } from '../components/AuditLogDetailPanel'
import { AuditLogEmpty, AuditLogNoMatches } from '../components/AuditLogEmptyStates'
import { AuditLogFilters } from '../components/AuditLogFilters'
import { AuditLogTable } from '../components/AuditLogTable'
import { useAuditLog } from '../hooks/useAuditLog'
import { useAuditLogFilters } from '../hooks/useAuditLogFilters'
import { useAuditLogOptions } from '../hooks/useAuditLogOptions'
import { detailIdFrom } from '../utils/filters'

/**
 * Bitácora: operaciones que dejaron huella en el sistema, de la más reciente a la más antigua. Solo lectura.
 *
 * Usuario, acción, módulo, rango de fechas y página viven en la URL, así que sobreviven a la recarga. El
 * detalle se abre en un panel sobre el listado (`?detalle=<id>`): cerrarlo quita ese parámetro y conserva
 * los filtros.
 */
export function AuditLogPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const {
    filters,
    searchInput,
    setSearchInput,
    setAction,
    setModule,
    setSince,
    setUntil,
    setPage,
    clear,
    hasActiveFilters,
    rangeError,
  } = useAuditLogFilters()
  const { options, status: optionsStatus } = useAuditLogOptions()
  const { page, isStale, status, error, reload } = useAuditLog(filters, rangeError === null)

  const detail = detailIdFrom(searchParams)
  const settled = page !== null && !isStale && status !== 'error'

  // Una acción o un módulo que ya no existe en el catálogo no filtra nada útil: se quita sin avisar.
  useEffect(() => {
    if (optionsStatus !== 'success') return

    if (filters.accion !== null && !options.acciones.some((action) => action.codigo === filters.accion)) {
      setAction(null, true)
    }
  }, [optionsStatus, options.acciones, filters.accion, setAction])

  useEffect(() => {
    if (optionsStatus !== 'success') return

    if (filters.modulo !== null && !options.modulos.some((module) => module.clave === filters.modulo)) {
      setModule(null, true)
    }
  }, [optionsStatus, options.modulos, filters.modulo, setModule])

  // Una página fuera de rango (por ejemplo, tras filtrar) vuelve a la última que existe.
  useEffect(() => {
    if (settled && page.items.length === 0 && filters.page > 1) {
      setPage(Math.max(1, page.totalPages), true)
    }
  }, [settled, page, filters.page, setPage])

  const closeDetail = () =>
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        next.delete('detalle')

        return next
      },
      { replace: true }
    )

  const hasRows = rangeError === null && page !== null && page.items.length > 0 && status !== 'error'
  const correctingPage = settled && page.items.length === 0 && filters.page > 1
  const showEmpty = rangeError === null && settled && page.items.length === 0 && !correctingPage

  return (
    <AppShell
      mobileTitle="Bitácora"
      breadcrumbs={[{ label: 'Historial, auditoría y reportes' }, { label: 'Bitácora' }]}
    >
      <PageHeader
        title="Bitácora"
        subtitle="Operaciones registradas en el sistema, de la más reciente a la más antigua. Solo lectura."
      />

      <div className="space-y-4">
        <AuditLogFilters
          searchInput={searchInput}
          onSearchChange={setSearchInput}
          accion={filters.accion}
          modulo={filters.modulo}
          desde={filters.desde}
          hasta={filters.hasta}
          options={options}
          optionsUnavailable={optionsStatus === 'error'}
          onActionChange={(accion) => setAction(accion)}
          onModuleChange={(modulo) => setModule(modulo)}
          onSinceChange={setSince}
          onUntilChange={setUntil}
          rangeError={rangeError}
        />

        {rangeError === null && (
          <Card className="overflow-hidden p-0" aria-busy={status === 'loading' || isStale}>
            {page === null && status === 'loading' && <LoadingState rows={6} label="Cargando operaciones" />}

            {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

            {correctingPage && <LoadingState rows={6} label="Cargando operaciones" />}

            {showEmpty && hasActiveFilters && <AuditLogNoMatches onClear={clear} />}

            {showEmpty && !hasActiveFilters && <AuditLogEmpty />}

            {hasRows && (
              <div className={cn(isStale && 'opacity-60 transition-opacity')}>
                <div className="hidden md:block">
                  <AuditLogTable entries={page.items} />
                </div>

                <AuditLogCardList entries={page.items} />

                <DataPagination
                  page={page.page}
                  perPage={page.perPage}
                  total={page.total}
                  totalPages={page.totalPages}
                  onPageChange={(next) => setPage(next)}
                  itemLabel="operaciones"
                  summaryPrefix=""
                  disabled={isStale}
                />
              </div>
            )}
          </Card>
        )}
      </div>

      {detail.present && <AuditLogDetailPanel id={detail.id} onClose={closeDetail} />}
    </AppShell>
  )
}
