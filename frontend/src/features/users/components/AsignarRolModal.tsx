import { useState } from 'react'
import { AlertTriangle, ArrowRightLeft, Lock } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
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
import { ApiError } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import { useRoles } from '../hooks/useRoles'
import { assignRole, getActiveAssignments } from '../services/usersService'
import {
  TEACHER_ROLE,
  accountFullName,
  accountInitials,
  type ActiveAssignments,
  type Role,
  type UserAccount,
} from '../types/users.types'
import { RoleBadge } from './RoleBadge'

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
  const selectedRole = roles.data?.find((role) => role.id_rol === selectedRoleId) ?? null

  const submit = async () => {
    if (!selectedRole) return

    setIsSubmitting(true)
    setErrorMessage(null)

    try {
      onAssigned(await assignRole(account.id_usuario, selectedRole.id_rol))
    } catch (cause) {
      setErrorMessage(describeError(cause))
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

    // Solo el rol Docente tiene grupos, materias o exámenes que puedan quedar sin responsable.
    if (currentRole.nombre_rol === TEACHER_ROLE) {
      setIsSubmitting(true)

      try {
        setAssignments(await getActiveAssignments(account.id_usuario))
      } catch (cause) {
        setErrorMessage(describeError(cause))
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
          aria-label={`De ${currentRole.nombre_rol} a ${selectedRole.nombre_rol}`}
        >
          <span className="text-muted-foreground">{currentRole.nombre_rol}</span>
          <ArrowRightLeft aria-hidden="true" className="size-4 text-subtle" />
          <span className="text-brand">{selectedRole.nombre_rol}</span>
        </div>

        {assignments?.tiene_activas && (
          <Alert className="border-warn-border bg-warn-soft text-warn-fg">
            <AlertTriangle aria-hidden="true" />
            <AlertTitle>Tiene asignaciones activas como {currentRole.nombre_rol}</AlertTitle>
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
          <p className="font-mono text-sm text-muted-foreground">{account.cod_sis}</p>
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

      <fieldset className="space-y-2">
        <legend className="mb-2 text-sm font-semibold text-foreground">
          Nuevo rol <span className="text-danger">*</span>
        </legend>

        {roles.status === 'loading' && <LoadingState rows={3} label="Cargando roles" />}

        {roles.status === 'error' && roles.error && (
          <ErrorState error={roles.error} onRetry={roles.reload} className="py-6" />
        )}

        {roles.status === 'success' &&
          roles.data?.map((role) => {
            const isCurrent = currentRole?.id_rol === role.id_rol
            const isSelected = selectedRoleId === role.id_rol

            return (
              <label
                key={role.id_rol}
                className={cn(
                  'flex items-start gap-3 rounded-lg border p-4 transition-colors',
                  isCurrent && 'cursor-not-allowed border-border-soft bg-sunken opacity-70',
                  !isCurrent && isSelected && 'cursor-pointer border-brand bg-brand-soft/40',
                  !isCurrent && !isSelected && 'cursor-pointer border-border-soft hover:border-border-strong'
                )}
              >
                <input
                  type="radio"
                  name="rol"
                  value={role.id_rol}
                  checked={isSelected}
                  disabled={isCurrent || isSubmitting}
                  onChange={() => setSelectedRoleId(role.id_rol)}
                  className="mt-1 accent-brand"
                />
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold text-foreground">{role.nombre_rol}</span>
                    {isCurrent && <span className="text-xs text-muted-foreground">Rol actual</span>}
                  </span>
                  {role.descripcion && (
                    <span className="mt-1 block text-sm text-muted-foreground">{role.descripcion}</span>
                  )}
                </span>
              </label>
            )
          })}
      </fieldset>

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

function describeError(cause: unknown): string {
  if (cause instanceof ApiError) {
    return cause.errors.id_rol?.[0] ?? cause.message
  }

  return 'No se pudo guardar el rol. Intente nuevamente.'
}

function plural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count === 1 ? singular : pluralForm}`
}
