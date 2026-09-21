import { useCallback, useMemo, useState } from 'react'
import { Search, SearchX, Users } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AccountsTable } from '../components/AccountsTable'
import { AsignarRolModal } from '../components/AsignarRolModal'
import { RegistrarCuentaModal } from '../components/RegistrarCuentaModal'
import { SuccessToast, type ToastMessage } from '../components/SuccessToast'
import { useUserAccounts } from '../hooks/useUserAccounts'
import { accountFullName, type Role, type UserAccount } from '../types/users.types'

type AccountFilter = 'todas' | 'sin-rol'

/** Lo que devuelve el registro de HU-001 al terminar: los datos que confirmó el SIS. */
interface RegisteredPerson {
  nombre: string
  paterno: string
}

/**
 * Cuentas con acceso a SCIEM y su rol vigente.
 *
 * Desde aquí se registran cuentas nuevas (HU-001) y se asigna el rol a las que
 * aún no tienen uno (HU-004).
 */
export function CuentasPage() {
  const { status, data, error, reload } = useUserAccounts()
  const [filter, setFilter] = useState<AccountFilter>('todas')
  const [search, setSearch] = useState('')
  const [isRegistering, setIsRegistering] = useState(false)
  const [accountToAssign, setAccountToAssign] = useState<UserAccount | null>(null)
  const [toast, setToast] = useState<ToastMessage | null>(null)

  const dismissToast = useCallback(() => setToast(null), [])

  const accounts = useMemo(() => data?.accounts ?? [], [data])
  const withoutRoleCount = accounts.filter((account) => !account.rol).length

  const visibleAccounts = useMemo(() => {
    const term = search.trim().toLowerCase()

    return accounts.filter((account) => {
      if (filter === 'sin-rol' && account.rol) return false
      if (!term) return true

      return [accountFullName(account), account.cod_sis, account.correo].some((value) =>
        value.toLowerCase().includes(term)
      )
    })
  }, [accounts, filter, search])

  const handleRegistered = (person: RegisteredPerson) => {
    setIsRegistering(false)
    setToast({
      title: 'Cuenta creada',
      description: `${person.nombre} ${person.paterno} quedó sin rol. Asígnele uno para que pueda ingresar.`,
    })
    reload()
  }

  const handleAssigned = (role: Role) => {
    const account = accountToAssign

    setAccountToAssign(null)
    setToast({
      title: account?.rol ? 'Rol actualizado' : 'Rol asignado',
      description: `${account ? account.nombre : 'La cuenta'} ahora tiene el rol ${role.nombre_rol}. Rige desde su siguiente acceso.`,
    })
    reload()
  }

  return (
    <AppShell mobileTitle="Cuentas" breadcrumbs={[{ label: 'Administración' }, { label: 'Cuentas' }]}>
      <SuccessToast message={toast} onDismiss={dismissToast} />

      <PageHeader
        title="Cuentas"
        subtitle="Personas con acceso a SCIEM. Una cuenta sin rol no puede usar las funciones del sistema."
        actions={<Button onClick={() => setIsRegistering(true)}>Registrar cuenta</Button>}
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Tabs value={filter} onValueChange={(value) => setFilter(value as AccountFilter)}>
          <TabsList>
            <TabsTrigger value="todas">Todas ({accounts.length})</TabsTrigger>
            <TabsTrigger value="sin-rol">Sin rol ({withoutRoleCount})</TabsTrigger>
          </TabsList>
        </Tabs>

        <div className="relative sm:w-80">
          <Search
            aria-hidden="true"
            className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
          />
          <Input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Buscar por nombre, código SIS o correo"
            aria-label="Buscar cuentas"
            className="pl-8"
          />
        </div>
      </div>

      <Card className="overflow-hidden p-0">
        {status === 'loading' && <LoadingState rows={5} label="Cargando cuentas" />}

        {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

        {status === 'success' && accounts.length === 0 && (
          <EmptyState
            icon={Users}
            title="Aún no hay cuentas registradas"
            description="Registre la primera cuenta para poder asignarle un rol."
          />
        )}

        {status === 'success' && accounts.length > 0 && visibleAccounts.length === 0 && (
          <EmptyState
            icon={SearchX}
            title="Ninguna cuenta coincide"
            description="Pruebe con otro nombre, código SIS o correo, o cambie el filtro."
          />
        )}

        {status === 'success' && visibleAccounts.length > 0 && (
          <AccountsTable
            accounts={visibleAccounts}
            currentUserId={data?.currentUserId ?? null}
            onAssignRole={setAccountToAssign}
          />
        )}
      </Card>

      <RegistrarCuentaModal
        isOpen={isRegistering}
        onClose={() => setIsRegistering(false)}
        onSuccess={handleRegistered}
      />

      <AsignarRolModal
        open={accountToAssign !== null}
        account={accountToAssign}
        onOpenChange={(open) => !open && setAccountToAssign(null)}
        onAssigned={handleAssigned}
      />
    </AppShell>
  )
}
