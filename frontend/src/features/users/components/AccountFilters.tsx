import { Search } from 'lucide-react'
import { FormSelect } from '@/components/common/FormSelect'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { MAX_SEARCH_LENGTH } from '../lib/accountFilters'
import { roleLabel } from '@/lib/roleLabel'
import { WITHOUT_ROLE_FILTER, type Role } from '../types/users.types'

const ALL_ROLES = 'todos'

/** Orden de las opciones del filtro; un rol nuevo del catálogo queda después de estos. */
const ROLE_ORDER = ['Administrador', 'Docente', 'Auxiliar', 'Invitado']

function orderOf(role: Role): number {
  const index = ROLE_ORDER.indexOf(role.nombre_rol)

  return index === -1 ? ROLE_ORDER.length : index
}

interface AccountFiltersProps {
  searchInput: string
  onSearchChange: (value: string) => void
  /** Rol elegido (`?rol=`: id o `sin-rol`), o null para todos. */
  role: string | null
  roles: Role[]
  /** El catálogo de roles no cargó: el filtro se deshabilita, el listado sigue funcionando. */
  rolesUnavailable: boolean
  onRoleChange: (role: string | null) => void
}

/** Búsqueda por nombre o código (SIS o INV) y filtro por rol activo del listado. */
export function AccountFilters({
  searchInput,
  onSearchChange,
  role,
  roles,
  rolesUnavailable,
  onRoleChange,
}: AccountFiltersProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row">
      <div className="relative sm:w-96">
        <Search
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          type="search"
          value={searchInput}
          maxLength={MAX_SEARCH_LENGTH}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder="Buscar por nombre o código"
          aria-label="Buscar por nombre o código"
          className="h-11 pl-8"
        />
      </div>

      <div className="sm:w-64">
        <Label htmlFor="cuentas-rol" className="sr-only">
          Rol activo
        </Label>
        <FormSelect
          id="cuentas-rol"
          value={role ?? ALL_ROLES}
          placeholder={rolesUnavailable ? 'Roles no disponibles' : 'Todos los roles'}
          disabled={rolesUnavailable}
          options={[
            { value: ALL_ROLES, label: 'Todos los roles' },
            ...[...roles]
              .sort((a, b) => orderOf(a) - orderOf(b))
              .map((item) => ({ value: String(item.id_rol), label: roleLabel(item.nombre_rol) })),
            { value: WITHOUT_ROLE_FILTER, label: 'Sin rol' },
          ]}
          onValueChange={(value) => onRoleChange(value === ALL_ROLES ? null : value)}
        />
      </div>
    </div>
  )
}
