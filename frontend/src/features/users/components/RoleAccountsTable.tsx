import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { GUEST_ROLE_LOCKED_MESSAGE, isGuestAccount } from '../lib/accountRoles'
import { accountFullName, type UserAccount } from '../types/users.types'
import { RoleBadge } from './RoleBadge'

interface RoleAccountsTableProps {
  accounts: UserAccount[]
  currentUserId: string | null
}

/**
 * Cuentas con su rol vigente, en tabla para escritorio y en tarjetas para móvil.
 *
 * Cada fila tiene una sola acción: «Modificar rol» o, si la cuenta no tiene ninguno, «Asignar rol».
 * En una cuenta de docente invitado el botón está deshabilitado: su rol no se cambia.
 */
export function RoleAccountsTable({ accounts, currentUserId }: RoleAccountsTableProps) {
  return (
    <TooltipProvider>
      <div className="hidden md:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="sciem-overline text-muted-foreground">Nombre</TableHead>
              <TableHead className="sciem-overline text-muted-foreground">Código SIS / INV</TableHead>
              <TableHead className="sciem-overline text-muted-foreground">Correo</TableHead>
              <TableHead className="sciem-overline text-muted-foreground">Rol activo</TableHead>
              <TableHead className="text-right">
                <span className="sr-only">Acción</span>
              </TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            {accounts.map((account) => (
              <TableRow key={account.id_usuario}>
                <TableCell>
                  <AccountName account={account} isOwn={account.id_usuario === currentUserId} />
                </TableCell>
                <TableCell className="font-mono">{account.cod_sis}</TableCell>
                <TableCell className="max-w-64 truncate" title={account.correo}>
                  {account.correo}
                </TableCell>
                <TableCell>
                  <RoleBadge role={account.rol} showTemporary />
                </TableCell>
                <TableCell className="text-right">
                  <AccountAction account={account} scope="tabla" />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <ul className="divide-y divide-border-soft md:hidden">
        {accounts.map((account) => (
          <li key={account.id_usuario} className="flex flex-col gap-3 p-4">
            <div className="flex items-start justify-between gap-3">
              <AccountName account={account} isOwn={account.id_usuario === currentUserId} />
              <RoleBadge role={account.rol} showTemporary />
            </div>
            <p className="font-mono text-sm text-muted-foreground">{account.cod_sis}</p>
            <p className="break-all text-sm text-muted-foreground">{account.correo}</p>
            <AccountAction account={account} scope="lista" />
          </li>
        ))}
      </ul>
    </TooltipProvider>
  )
}

function AccountName({ account, isOwn }: { account: UserAccount; isOwn: boolean }) {
  return (
    <span className="flex min-w-0 flex-wrap items-center gap-2 font-semibold text-foreground">
      {accountFullName(account)}
      {isOwn && <Badge className="bg-info-soft text-info">Su cuenta</Badge>}
      {account.estado === 'INACTIVO' && <Badge className="bg-bg-app text-dis-text">Deshabilitada</Badge>}
    </span>
  )
}

function AccountAction({ account, scope }: { account: UserAccount; scope: 'tabla' | 'lista' }) {
  const name = accountFullName(account)
  const label = account.rol ? 'Modificar rol' : 'Asignar rol'

  if (isGuestAccount(account)) {
    const noticeId = `rol-bloqueado-${scope}-${account.id_usuario}`

    // Un botón deshabilitado no recibe el foco: el aviso se ancla a un contenedor que sí lo recibe.
    return (
      <div className="flex md:justify-end">
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0} aria-describedby={noticeId} className="inline-flex rounded-lg">
              <Button
                size="sm"
                disabled
                aria-label={`${label} de ${name}`}
                aria-describedby={noticeId}
                className="disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100"
              >
                {label}
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent>{GUEST_ROLE_LOCKED_MESSAGE}</TooltipContent>
        </Tooltip>
        <span id={noticeId} className="sr-only">
          {GUEST_ROLE_LOCKED_MESSAGE}
        </span>
      </div>
    )
  }

  return (
    <div className="flex md:justify-end">
      <Button variant={account.rol ? 'default' : 'warning'} size="sm" asChild>
        <Link to={`/cuentas/${account.id_usuario}`} aria-label={`${label} de ${name}`}>
          {label}
        </Link>
      </Button>
    </div>
  )
}
