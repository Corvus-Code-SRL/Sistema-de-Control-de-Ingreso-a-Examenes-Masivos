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
import { accountFullName, type UserAccount } from '../types/users.types'
import { RoleBadge } from './RoleBadge'

interface AccountsTableProps {
  accounts: UserAccount[]
  currentUserId: string | null
  onAssignRole: (account: UserAccount) => void
}

/**
 * Cuentas en tabla para escritorio y en tarjetas para móvil.
 *
 * La cuenta propia no ofrece asignar rol: un Administrador no puede modificar
 * el suyo.
 */
export function AccountsTable({ accounts, currentUserId, onAssignRole }: AccountsTableProps) {
  return (
    <>
      <div className="hidden md:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="sciem-overline text-muted-foreground">Persona</TableHead>
              <TableHead className="sciem-overline text-muted-foreground">Código SIS</TableHead>
              <TableHead className="sciem-overline text-muted-foreground">Rol</TableHead>
              <TableHead className="text-right">
                <span className="sr-only">Acciones</span>
              </TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            {accounts.map((account) => (
              <TableRow key={account.id_usuario}>
                <TableCell>
                  <AccountIdentity account={account} isOwn={account.id_usuario === currentUserId} />
                </TableCell>
                <TableCell className="font-mono">{account.cod_sis}</TableCell>
                <TableCell>
                  <RoleBadge role={account.rol} />
                </TableCell>
                <TableCell className="text-right">
                  <AccountActions
                    account={account}
                    isOwn={account.id_usuario === currentUserId}
                    onAssignRole={onAssignRole}
                  />
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
              <AccountIdentity account={account} isOwn={account.id_usuario === currentUserId} />
              <RoleBadge role={account.rol} />
            </div>
            <p className="font-mono text-sm text-muted-foreground">{account.cod_sis}</p>
            <div className="flex justify-end">
              <AccountActions
                account={account}
                isOwn={account.id_usuario === currentUserId}
                onAssignRole={onAssignRole}
              />
            </div>
          </li>
        ))}
      </ul>
    </>
  )
}

function AccountIdentity({ account, isOwn }: { account: UserAccount; isOwn: boolean }) {
  return (
    <div className="flex min-w-0 flex-col">
      <span className="flex flex-wrap items-center gap-2 font-semibold text-foreground">
        {accountFullName(account)}
        {isOwn && <Badge className="bg-info-soft text-info">Su cuenta</Badge>}
        {account.estado === 'INACTIVO' && (
          <Badge className="bg-bg-app text-dis-text">Deshabilitada</Badge>
        )}
      </span>
      <span className="truncate text-sm text-muted-foreground">{account.correo}</span>
    </div>
  )
}

interface AccountActionsProps {
  account: UserAccount
  isOwn: boolean
  onAssignRole: (account: UserAccount) => void
}

function AccountActions({ account, isOwn, onAssignRole }: AccountActionsProps) {
  return (
    <div className="flex flex-wrap justify-end gap-2">
      {!account.rol && !isOwn && (
        <Button size="sm" onClick={() => onAssignRole(account)}>
          Asignar rol
        </Button>
      )}
      <Button variant="outline" size="sm" asChild>
        <Link to={`/cuentas/${account.id_usuario}`}>Ver detalle</Link>
      </Button>
    </div>
  )
}
