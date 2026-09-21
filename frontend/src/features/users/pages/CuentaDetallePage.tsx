import { useCallback, useState } from 'react'
import { ArrowRightLeft, SearchX } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { AsignarRolModal } from '../components/AsignarRolModal'
import { RoleBadge } from '../components/RoleBadge'
import { SuccessToast, type ToastMessage } from '../components/SuccessToast'
import { useUserAccount } from '../hooks/useUserAccount'
import {
  accountFullName,
  accountInitials,
  type Role,
  type RoleHistoryEntry,
} from '../types/users.types'

const SELF_EDIT_MESSAGE = 'No puede cambiar el rol de su propia cuenta. Pídalo a otro administrador.'

/**
 * Detalle de una cuenta: rol vigente, cambio de rol e historial.
 *
 * Solo contiene lo de HU-004; la edición de datos, la deshabilitación y la
 * bitácora llegan con sus propias historias.
 */
export function CuentaDetallePage() {
  const { idUsuario = '' } = useParams()
  const { detail, isLoading, isNotFound, error, reload } = useUserAccount(idUsuario)
  const [isAssigning, setIsAssigning] = useState(false)
  const [toast, setToast] = useState<ToastMessage | null>(null)

  const dismissToast = useCallback(() => setToast(null), [])

  const account = detail?.account ?? null
  const isOwnAccount = account !== null && account.id_usuario === detail?.currentUserId
  const title = account ? accountFullName(account) : 'Detalle de la cuenta'

  const handleAssigned = (role: Role) => {
    setIsAssigning(false)
    setToast({
      title: account?.rol ? 'Rol actualizado' : 'Rol asignado',
      description: `${account?.nombre ?? 'La cuenta'} ahora tiene el rol ${role.nombre_rol}. Rige desde su siguiente acceso.`,
    })
    reload()
  }

  return (
    <AppShell
      mobileTitle="Cuenta"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Cuentas', to: '/cuentas' },
        { label: title },
      ]}
    >
      <SuccessToast message={toast} onDismiss={dismissToast} />

      <PageHeader title="Detalle de la cuenta" backTo="/cuentas" backLabel="Volver a Cuentas" />

      {isLoading && (
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando la cuenta" />
        </Card>
      )}

      {!isLoading && isNotFound && (
        <Card className="p-0">
          <EmptyState
            icon={SearchX}
            title="La cuenta no existe"
            description="La cuenta que intenta abrir no está registrada en SCIEM."
            action={
              <Button variant="outline" size="sm" asChild>
                <Link to="/cuentas">Ir a Cuentas</Link>
              </Button>
            }
          />
        </Card>
      )}

      {!isLoading && error && (
        <Card className="p-0">
          <ErrorState error={error} onRetry={reload} />
        </Card>
      )}

      {!isLoading && account && (
        <div className="flex flex-col gap-5">
          <Card>
            <CardContent className="flex items-center gap-4">
              <span
                aria-hidden="true"
                className="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xl font-semibold text-brand-deep"
              >
                {accountInitials(account)}
              </span>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="sciem-h2 truncate">{title}</h2>
                  {account.estado === 'ACTIVO' ? (
                    <Badge className="bg-ok-soft text-ok-fg">Activa</Badge>
                  ) : (
                    <Badge className="bg-bg-app text-dis-text">Deshabilitada</Badge>
                  )}
                  {isOwnAccount && <Badge className="bg-info-soft text-info">Su cuenta</Badge>}
                </div>
                <p className="mt-1 truncate text-sm text-muted-foreground">
                  <span className="font-mono text-foreground">{account.cod_sis}</span> · {account.correo}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
              <CardTitle className="sciem-h3">Rol</CardTitle>
              <ChangeRoleButton
                hasRole={account.rol !== null}
                isOwnAccount={isOwnAccount}
                onClick={() => setIsAssigning(true)}
              />
            </CardHeader>
            <CardContent>
              {account.rol ? (
                <div className="flex flex-wrap items-center gap-3">
                  <RoleBadge role={account.rol} />
                  <ActiveSince history={account.historial_roles} />
                </div>
              ) : (
                <p className="rounded-lg bg-warn-soft p-3 text-sm font-semibold text-warn-fg">
                  Esta cuenta no tiene ningún rol asignado. No puede usar las funciones de SCIEM.
                </p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="sciem-h3">Historial de roles</CardTitle>
            </CardHeader>
            <CardContent>
              <RoleHistory history={account.historial_roles} />
            </CardContent>
          </Card>
        </div>
      )}

      <AsignarRolModal
        open={isAssigning}
        account={account}
        onOpenChange={setIsAssigning}
        onAssigned={handleAssigned}
      />
    </AppShell>
  )
}

interface ChangeRoleButtonProps {
  hasRole: boolean
  isOwnAccount: boolean
  onClick: () => void
}

function ChangeRoleButton({ hasRole, isOwnAccount, onClick }: ChangeRoleButtonProps) {
  const label = hasRole ? 'Cambiar rol' : 'Asignar rol'

  if (!isOwnAccount) {
    return (
      <Button variant="outline" size="sm" onClick={onClick}>
        <ArrowRightLeft aria-hidden="true" />
        {label}
      </Button>
    )
  }

  // Un botón deshabilitado no recibe el foco: el aviso se ancla a un contenedor que sí.
  return (
    <div className="flex flex-col items-end gap-1">
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0}>
              <Button variant="outline" size="sm" disabled>
                <ArrowRightLeft aria-hidden="true" />
                {label}
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent>{SELF_EDIT_MESSAGE}</TooltipContent>
        </Tooltip>
      </TooltipProvider>
      <span className="sr-only">{SELF_EDIT_MESSAGE}</span>
    </div>
  )
}

function ActiveSince({ history }: { history: RoleHistoryEntry[] }) {
  const active = history.find((entry) => entry.fecha_fin === null)

  if (!active) return null

  return <span className="text-sm text-muted-foreground">Desde el {formatDate(active.fecha_inicio)}</span>
}

function RoleHistory({ history }: { history: RoleHistoryEntry[] }) {
  if (history.length === 0) {
    return <p className="text-sm text-muted-foreground">Aún no hay registros en el historial de roles.</p>
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Rol</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Desde</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Hasta</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {history.map((entry) => (
          <TableRow key={`${entry.id_rol}-${entry.fecha_inicio}`}>
            <TableCell>{entry.nombre_rol}</TableCell>
            <TableCell>{formatDate(entry.fecha_inicio)}</TableCell>
            <TableCell>
              {entry.fecha_fin ? (
                formatDate(entry.fecha_fin)
              ) : (
                <Badge className="bg-ok-soft text-ok-fg">Vigente</Badge>
              )}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
