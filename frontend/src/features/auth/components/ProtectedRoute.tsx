import type { ReactNode } from 'react'
import { AppShell } from '@/components/layout/AppShell'
import { PermissionDenied } from '@/components/common/PermissionDenied'
import { hasCapability, type Capability } from '../capabilities'
import { useAuth } from '../hooks/useAuth'

interface ProtectedRouteProps {
  /** Lo que la ruta exige: una capacidad, nunca un nombre de rol. */
  capability: Capability
  children: ReactNode
}

/**
 * Deja pasar solo a quien tiene la capacidad que la ruta pide.
 *
 * SIN SESIÓN DEJA PASAR TODO: hasta la fase 3 las rutas del backend no están protegidas y la app
 * debe seguir funcionando como el usuario fijo. Con sesión, quien no tiene la capacidad ve el aviso
 * de permiso en el mismo lugar: no se le cierra la sesión ni se le manda al login.
 *
 * No decide si el docente puede actuar sobre UN examen concreto (dueño, auxiliar habilitado o
 * invitado): eso es una política del backend.
 */
export function ProtectedRoute({ capability, children }: ProtectedRouteProps) {
  const { estado, rol } = useAuth()

  const hasSession = estado === 'autenticado' || estado === 'expirada'

  if (!hasSession || hasCapability(rol?.nombre_rol, capability)) {
    return <>{children}</>
  }

  return (
    <AppShell
      mobileTitle="Sin permiso"
      breadcrumbs={[{ label: 'Acceso restringido' }]}
    >
      <PermissionDenied />
    </AppShell>
  )
}
