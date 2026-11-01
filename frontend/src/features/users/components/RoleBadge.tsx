import { Badge } from '@/components/ui/badge'
import { roleLabel } from '@/lib/roleLabel'
import { GUEST_ROLE, type AccountRole } from '../types/users.types'

interface RoleBadgeProps {
  role: AccountRole | null
  /** Marca el rol del docente invitado como temporal, como en el listado de cuentas. */
  showTemporary?: boolean
}

/** Rol vigente de una cuenta, o el aviso de que aún no tiene ninguno. */
export function RoleBadge({ role, showTemporary = false }: RoleBadgeProps) {
  if (!role) {
    return <Badge className="bg-warn-soft text-warn-fg">Sin rol</Badge>
  }

  const label = roleLabel(role.nombre_rol)

  return (
    <Badge className="bg-brand-soft text-brand-deep">
      {showTemporary && role.nombre_rol === GUEST_ROLE ? `${label} (temporal)` : label}
    </Badge>
  )
}
