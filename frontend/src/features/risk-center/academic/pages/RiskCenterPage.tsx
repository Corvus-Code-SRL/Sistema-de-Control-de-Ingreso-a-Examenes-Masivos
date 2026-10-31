import { useEffect } from 'react'
import { Navigate, useLocation, useNavigate, useParams } from 'react-router-dom'
import { DataPagination } from '@/components/common/DataPagination'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card } from '@/components/ui/card'
import { useCurrentUser } from '@/features/auth'
import { cn } from '@/lib/utils'
import { RiskCenterCardList } from '../components/RiskCenterCardList'
import { RiskCenterDetailPanel } from '../components/RiskCenterDetailPanel'
import { RiskCenterEmptyRegistry, RiskCenterNoMatches } from '../components/RiskCenterEmptyStates'
import { RiskCenterFilters } from '../components/RiskCenterFilters'
import { RiskCenterTable } from '../components/RiskCenterTable'
import { useFaultTypes } from '../hooks/useFaultTypes'
import { useRiskCenterFilters } from '../hooks/useRiskCenterFilters'
import { useRiskCenterRecords } from '../hooks/useRiskCenterRecords'

const LIST_PATH = '/central-riesgo'

/** `:id` de la URL como id de registro; null si no hay o no es un entero positivo del rango de la base. */
function recordIdFrom(param: string | undefined): number | null {
  if (param === undefined || !/^[1-9]\d{0,9}$/.test(param)) return null

  const id = Number(param)

  return id <= 2147483647 ? id : null
}

/**
 * Central de Riesgo: antecedentes confirmados de toda la institución, de solo lectura.
 *
 * Búsqueda, tipo de falta y página viven en la URL. El detalle se abre en un panel sobre el listado
 * (`/central-riesgo/:id`): cerrarlo vuelve a `/central-riesgo` con los mismos filtros.
 */
export function RiskCenterPage() {
  const { id: idParam } = useParams<{ id: string }>()
  const { search } = useLocation()
  const navigate = useNavigate()
  const { area } = useCurrentUser()

  const { filters, searchInput, setSearchInput, setType, setPage, clear, hasActiveFilters } =
    useRiskCenterFilters()
  const { faultTypes, status: typesStatus } = useFaultTypes()
  const { page, isStale, status, error, reload } = useRiskCenterRecords(filters)

  const openId = recordIdFrom(idParam)
  const settled = page !== null && !isStale && status !== 'error'

  // Un tipo que ya no existe en el catálogo no filtra nada útil: se quita sin avisar.
  useEffect(() => {
    if (
      typesStatus === 'success' &&
      filters.tipo !== null &&
      !faultTypes.some((faultType) => faultType.id_falta === filters.tipo)
    ) {
      setType(null, true)
    }
  }, [typesStatus, faultTypes, filters.tipo, setType])

  // Una página fuera de rango (por ejemplo, tras filtrar) vuelve a la última que existe.
  useEffect(() => {
    if (settled && page.items.length === 0 && filters.page > 1) {
      setPage(Math.max(1, page.totalPages), true)
    }
  }, [settled, page, filters.page, setPage])

  if (idParam !== undefined && openId === null) {
    return <Navigate to={{ pathname: LIST_PATH, search }} replace />
  }

  const closeDetail = () => navigate({ pathname: LIST_PATH, search })

  const isAdministrator = area === 'administrador'
  const hasRows = page !== null && page.items.length > 0 && status !== 'error'
  const correctingPage = settled && page.items.length === 0 && filters.page > 1

  return (
    <AppShell
      mobileTitle={isAdministrator ? 'Central de riesgo' : 'Registros'}
      breadcrumbs={
        isAdministrator
          ? [{ label: 'Supervisión' }, { label: 'Central de riesgo' }]
          : [{ label: 'Central de riesgo' }, { label: 'Registros' }]
      }
    >
      <PageHeader
        title="Central de Riesgo"
        subtitle="Antecedentes confirmados por doble comprobación, de toda la institución. Solo lectura."
      />

      <div className="space-y-4">
        <RiskCenterFilters
          searchInput={searchInput}
          onSearchChange={setSearchInput}
          type={filters.tipo}
          faultTypes={faultTypes}
          typesUnavailable={typesStatus === 'error'}
          onTypeChange={(type) => setType(type)}
        />

        <Card className="overflow-hidden p-0" aria-busy={status === 'loading' || isStale}>
          {page === null && status === 'loading' && <LoadingState rows={6} label="Cargando registros" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {correctingPage && <LoadingState rows={6} label="Cargando registros" />}

          {settled && page.items.length === 0 && !correctingPage && hasActiveFilters && (
            <RiskCenterNoMatches onClear={clear} />
          )}

          {settled && page.items.length === 0 && !correctingPage && !hasActiveFilters && (
            <RiskCenterEmptyRegistry />
          )}

          {hasRows && (
            <div className={cn(isStale && 'opacity-60 transition-opacity')}>
              <div className="hidden md:block">
                <RiskCenterTable records={page.items} />
              </div>

              <RiskCenterCardList records={page.items} />

              <DataPagination
                page={page.page}
                perPage={page.perPage}
                total={page.total}
                totalPages={page.totalPages}
                onPageChange={(next) => setPage(next)}
                itemLabel="registros"
                summaryPrefix=""
                disabled={isStale}
              />
            </div>
          )}
        </Card>
      </div>

      {openId !== null && <RiskCenterDetailPanel id={openId} onClose={closeDetail} />}
    </AppShell>
  )
}
