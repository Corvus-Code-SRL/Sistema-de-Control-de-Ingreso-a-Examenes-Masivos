import { Badge } from '@/components/ui/badge'
import type { AccountRole } from '../types/users.types'

/** Rol vigente de una cuenta, o el aviso de que aún no tiene ninguno. */
export function RoleBadge({ role }: { role: AccountRole | null }) {
  if (!role) {
    return <Badge className="bg-warn-soft text-warn-fg">Sin rol</Badge>
  }

  return <Badge className="bg-brand-soft text-brand-deep">{role.nombre_rol}</Badge>
}
