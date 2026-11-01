import { useState } from 'react'
import { AlertTriangle, ArrowRightLeft, Lock } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { roleLabel } from '@/lib/roleLabel'
import { useRoles } from '../hooks/useRoles'
import { rolesAllowedFor } from '../lib/accountRoles'
import { describeRoleError } from '../lib/describeRoleError'
import { assignRole, getActiveAssignments } from '../services/usersService'
import {
  TEACHING_ROLES,
  accountFullName,
  accountInitials,
  type ActiveAssignments,
  type Role,
  type UserAccount,
} from '../types/users.types'
import { RoleBadge } from './RoleBadge'
import { RoleOptions } from './RoleOptions'

interface AsignarRolModalProps {
  open: boolean
  account: UserAccount | null
  onOpenChange: (open: boolean) => void
  /** Se llama con el rol que quedó vigente, una vez guardado en el servidor. */
  onAssigned: (role: Role) => void
}

/**
 * Asignación o cambio del rol de una cuenta.
 *
 * Una cuenta sin rol lo recibe directamente. Si ya tiene uno, el cambio pasa por
 * una confirmación que muestra el rol actual y el nuevo y, cuando la cuenta es
 * Docente, avisa de los grupos, materias y exámenes que tiene a su cargo.
 */
export function AsignarRolModal({ open, account, onOpenChange, onAssigned }: AsignarRolModalProps) {
  return (
    <Dialog open={open && account !== null} onOpenChange={onOpenChange}>
      <DialogContent className="gap-5 sm:max-w-xl">
        {account && (
          <RoleAssignment
            key={account.id_usuario}
            account={account}
            onCancel={() => onOpenChange(false)}
            onAssigned={onAssigned}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

type Step = 'select' | 'confirm'

interface RoleAssignmentProps {
  account: UserAccount
  onCancel: () => void
  onAssigned: (role: Role) => void
}

function RoleAssignment({ account, onCancel, onAssigned }: RoleAssignmentProps) {
  const roles = useRoles()
  const [step, setStep] = useState<Step>('select')
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null)
  const [assignments, setAssignments] = useState<ActiveAssignments | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const currentRole = account.rol
  // Una cuenta de docente invitado solo admite el rol Invitado y una con código SIS nunca lo admite.
  const allowedRoles = { ...roles, data: roles.data ? rolesAllowedFor(roles.data, account) : null }
  const selectedRole = allowedRoles.data?.find((role) => role.id_rol === selectedRoleId) ?? null

  const submit = async () => {
    if (!selectedRole) return

    setIsSubmitting(true)
    setErrorMessage(null)

    try {
      onAssigned(await assignRole(account.id_usuario, selectedRole.id_rol))
    } catch (cause) {
      setErrorMessage(describeRoleError(cause))
      setStep('select')
    } finally {
      setIsSubmitting(false)
    }
  }

  const continueToConfirmation = async () => {
    if (!selectedRole) return

    if (!currentRole) {
      await submit()
      return
    }

    setErrorMessage(null)

    // Solo los roles que ejercen la docencia tienen grupos, materias o exámenes que puedan quedar sin responsable.
    if (TEACHING_ROLES.includes(currentRole.nombre_rol)) {
      setIsSubmitting(true)

      try {
        setAssignments(await getActiveAssignments(account.id_usuario))
      } catch (cause) {
        setErrorMessage(describeRoleError(cause))
        return
      } finally {
        setIsSubmitting(false)
      }
    }

    setStep('confirm')
  }

  if (step === 'confirm' && currentRole && selectedRole) {
    return (
      <>
        <DialogHeader>
          <DialogTitle className="sciem-h2">¿Cambiar el rol de {account.nombre}?</DialogTitle>
          <DialogDescription>Revise el cambio antes de confirmarlo.</DialogDescription>
        </DialogHeader>

        <div
          className="flex items-center justify-center gap-4 rounded-lg border border-border-soft bg-sunken py-3 text-sm font-semibold"
          aria-label={`De ${roleLabel(currentRole.nombre_rol)} a ${roleLabel(selectedRole.nombre_rol)}`}
        >
          <span className="text-muted-foreground">{roleLabel(currentRole.nombre_rol)}</span>
          <ArrowRightLeft aria-hidden="true" className="size-4 text-subtle" />
          <span className="text-brand">{roleLabel(selectedRole.nombre_rol)}</span>
        </div>

        {assignments?.tiene_activas && (
          <Alert className="border-warn-border bg-warn-soft text-warn-fg">
            <AlertTriangle aria-hidden="true" />
            <AlertTitle>Tiene asignaciones activas como {roleLabel(currentRole.nombre_rol)}</AlertTitle>
            <AlertDescription className="text-warn-fg">
              <ul className="list-disc space-y-1 pl-5">
                <li>{plural(assignments.grupos, 'grupo', 'grupos')} en el periodo actual.</li>
                <li>{plural(assignments.materias, 'materia', 'materias')} con esos grupos.</li>
                {assignments.examenes > 0 && (
                  <li>{plural(assignments.examenes, 'examen programado', 'exámenes programados')}.</li>
                )}
              </ul>
              <p className="mt-2">
                Al cambiar de rol dejará de poder gestionarlos. Reasígnelos antes o después del cambio.
              </p>
            </AlertDescription>
          </Alert>
        )}

        <p className="rounded-lg border border-border-soft bg-sunken p-3 text-sm text-muted-foreground">
          Si tiene una sesión activa, el nuevo rol se aplicará en su siguiente inicio de sesión.
        </p>

        <DialogFooter>
          <Button variant="outline" onClick={() => setStep('select')} disabled={isSubmitting}>
            Volver
          </Button>
          <Button onClick={submit} disabled={isSubmitting}>
            <ArrowRightLeft aria-hidden="true" />
            {isSubmitting ? 'Guardando…' : 'Cambiar rol'}
          </Button>
        </DialogFooter>
      </>
    )
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle className="sciem-h2">{currentRole ? 'Cambiar rol' : 'Asignar rol'}</DialogTitle>
        <DialogDescription>Una cuenta tiene un solo rol a la vez.</DialogDescription>
      </DialogHeader>

      <div className="flex items-center gap-3 rounded-lg border border-border-soft bg-sunken p-3">
        <span
          aria-hidden="true"
          className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft font-semibold text-brand"
        >
          {accountInitials(account)}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold text-foreground">{accountFullName(account)}</p>
          <p className="font-mono text-sm text-muted-foreground">
            {account.es_invitado ? 'Código INV ' : 'Código SIS '}
            {account.cod_sis}
          </p>
        </div>
        <RoleBadge role={currentRole} />
      </div>

      {account.estado === 'INACTIVO' && (
        <Alert className="border-info-border bg-info-soft text-info">
          <Lock aria-hidden="true" />
          <AlertTitle>La cuenta está deshabilitada</AlertTitle>
          <AlertDescription className="text-info">
            Asignar o cambiar el rol no la habilita. Seguirá sin poder ingresar hasta que se habilite.
          </AlertDescription>
        </Alert>
      )}

      {errorMessage && (
        <Alert variant="destructive" className="border-danger-soft bg-danger-soft text-danger-fg">
          <AlertTriangle aria-hidden="true" />
          <AlertDescription className="text-danger-fg">{errorMessage}</AlertDescription>
        </Alert>
      )}

      <RoleOptions
        roles={allowedRoles}
        currentRoleId={currentRole?.id_rol}
        selectedRoleId={selectedRoleId}
        disabled={isSubmitting}
        onSelect={setSelectedRoleId}
      />

      <DialogFooter>
        <Button variant="outline" onClick={onCancel} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button onClick={continueToConfirmation} disabled={!selectedRole || isSubmitting}>
          {isSubmitting ? 'Procesando…' : currentRole ? 'Continuar' : 'Asignar rol'}
        </Button>
      </DialogFooter>
    </>
  )
}

function plural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count === 1 ? singular : pluralForm}`
}
