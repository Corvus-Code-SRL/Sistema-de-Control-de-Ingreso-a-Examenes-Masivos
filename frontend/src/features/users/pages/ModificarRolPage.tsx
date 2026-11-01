import { useEffect } from 'react'
import { SearchX, Users } from 'lucide-react'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { AccountFilters } from '../components/AccountFilters'
import { RoleAccountsTable } from '../components/RoleAccountsTable'
import { useAccountFilters } from '../hooks/useAccountFilters'
import { useRoles } from '../hooks/useRoles'
import { useUserAccounts } from '../hooks/useUserAccounts'
import { WITHOUT_ROLE_FILTER } from '../types/users.types'

/**
 * Modificar rol (HU-004): cuentas con su rol activo, para cambiarlo o asignarlo.
 *
 * Búsqueda, rol y página viven en la URL y los resuelve el servidor. «Modificar rol» lleva a la vista
 * de cambio de rol de la cuenta, con su historial.
 */
export function ModificarRolPage() {
  const { filters, searchInput, setSearchInput, setRole, setPage, clear, hasActiveFilters } =
    useAccountFilters()
  const roles = useRoles()
  const { accounts, isStale, status, error, reload } = useUserAccounts(filters)

  const settled = accounts !== null && !isStale && status !== 'error'

  // Un rol que ya no existe en el catálogo no filtra nada útil: se quita sin avisar.
  useEffect(() => {
    if (
      roles.status === 'success' &&
      filters.rol !== null &&
      filters.rol !== WITHOUT_ROLE_FILTER &&
      !roles.data?.some((role) => String(role.id_rol) === filters.rol)
    ) {
      setRole(null, true)
    }
  }, [roles.status, roles.data, filters.rol, setRole])

  // Una página fuera de rango (por ejemplo, tras filtrar) vuelve a la última que existe.
  useEffect(() => {
    if (settled && accounts.accounts.length === 0 && filters.page > 1) {
      setPage(Math.max(1, accounts.totalPages), true)
    }
  }, [settled, accounts, filters.page, setPage])

  const hasRows = accounts !== null && accounts.accounts.length > 0 && status !== 'error'
  const correctingPage = settled && accounts.accounts.length === 0 && filters.page > 1

  return (
    <AppShell mobileTitle="Modificar rol" breadcrumbs={[{ label: 'Administración' }, { label: 'Modificar rol' }]}>
      <PageHeader
        title="Modificar rol"
        subtitle="Cuentas con su rol activo. Una cuenta sin rol no puede usar las funciones del sistema."
      />

      <div className="space-y-4">
        <AccountFilters
          searchInput={searchInput}
          onSearchChange={setSearchInput}
          role={filters.rol}
          roles={roles.data ?? []}
          rolesUnavailable={roles.status === 'error'}
          onRoleChange={(role) => setRole(role)}
        />

        <Card className="overflow-hidden p-0" aria-busy={status === 'loading' || isStale}>
          {accounts === null && status === 'loading' && <LoadingState rows={5} label="Cargando cuentas" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {correctingPage && <LoadingState rows={5} label="Cargando cuentas" />}

          {settled && accounts.accounts.length === 0 && !correctingPage && hasActiveFilters && (
            <EmptyState
              icon={SearchX}
              title="Ninguna cuenta coincide"
              description="Pruebe con otro nombre o código, o cambie el filtro de rol."
              action={
                <Button variant="outline" size="sm" onClick={clear}>
                  Limpiar filtros
                </Button>
              }
            />
          )}

          {settled && accounts.accounts.length === 0 && !correctingPage && !hasActiveFilters && (
            <EmptyState
              icon={Users}
              title="Aún no hay cuentas registradas"
              description="Registre la primera cuenta para poder asignarle un rol."
            />
          )}

          {hasRows && (
            <div className={cn(isStale && 'opacity-60 transition-opacity')}>
              <RoleAccountsTable
                accounts={accounts.accounts}
                currentUserId={accounts.currentUserId}
              />

              <DataPagination
                page={accounts.page}
                perPage={accounts.perPage}
                total={accounts.total}
                totalPages={accounts.totalPages}
                onPageChange={(next) => setPage(next)}
                itemLabel="cuentas"
                summaryPrefix=""
                disabled={isStale}
              />
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  )
}
