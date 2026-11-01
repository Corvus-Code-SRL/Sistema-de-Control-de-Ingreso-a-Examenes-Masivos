import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import type { AsyncResource } from '@/hooks/useAsyncResource'
import { cn } from '@/lib/utils'
import { roleLabel } from '@/lib/roleLabel'
import type { Role } from '../types/users.types'

interface RoleOptionsProps {
  roles: AsyncResource<Role[]>
  /** Rol vigente de la cuenta: se muestra, pero no se puede volver a elegir. */
  currentRoleId?: number | null
  selectedRoleId: number | null
  disabled?: boolean
  /** Título del grupo de opciones. */
  legend?: string
  /** Motivo por el que un rol no se puede elegir (la opción se deshabilita y lo muestra), o `null`. */
  unavailableReason?: (role: Role) => string | null
  onSelect: (roleId: number) => void
}

/**
 * Lista de roles asignables con su descripción, en tarjetas seleccionables.
 *
 * La comparten el cambio de rol de una cuenta (HU-004) y el paso «Rol» del registro (HU-001): ambas
 * pintan el mismo catálogo y eligen uno solo.
 */
export function RoleOptions({
  roles,
  currentRoleId = null,
  selectedRoleId,
  disabled = false,
  legend = 'Nuevo rol',
  unavailableReason,
  onSelect,
}: RoleOptionsProps) {
  return (
    <fieldset className="space-y-2">
      <legend className="mb-2 text-sm font-semibold text-foreground">
        {legend} <span className="text-danger">*</span>
      </legend>

      {roles.status === 'loading' && <LoadingState rows={3} label="Cargando roles" />}

      {roles.status === 'error' && roles.error && (
        <ErrorState error={roles.error} onRetry={roles.reload} className="py-6" />
      )}

      {roles.status === 'success' &&
        roles.data?.map((role) => {
          const reason = unavailableReason?.(role) ?? null
          const isCurrent = currentRoleId === role.id_rol
          const isLocked = isCurrent || reason !== null
          const isSelected = selectedRoleId === role.id_rol && !isLocked
          const hintId = `rol-${role.id_rol}-motivo`

          return (
            <label
              key={role.id_rol}
              className={cn(
                'flex items-start gap-3 rounded-lg border p-4 transition-colors',
                isLocked && 'cursor-not-allowed border-border-soft bg-sunken opacity-70',
                !isLocked && isSelected && 'cursor-pointer border-brand bg-brand-soft/40',
                !isLocked && !isSelected && 'cursor-pointer border-border-soft hover:border-border-strong'
              )}
            >
              <input
                type="radio"
                name="rol"
                value={role.id_rol}
                checked={isSelected}
                disabled={isLocked || disabled}
                aria-describedby={reason ? hintId : undefined}
                onChange={() => onSelect(role.id_rol)}
                className="mt-1 accent-brand"
              />
              <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-semibold text-foreground">{roleLabel(role.nombre_rol)}</span>
                  {isCurrent && <span className="text-xs text-muted-foreground">Rol actual</span>}
                </span>
                {role.descripcion && (
                  <span className="mt-1 block text-sm text-muted-foreground">{role.descripcion}</span>
                )}
                {reason && (
                  <span id={hintId} className="mt-1 block text-sm font-medium text-warn-fg">
                    {reason}
                  </span>
                )}
              </span>
            </label>
          )
        })}
    </fieldset>
  )
}
