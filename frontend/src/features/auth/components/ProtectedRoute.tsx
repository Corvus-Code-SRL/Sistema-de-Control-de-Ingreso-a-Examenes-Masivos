import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { PermissionDenied } from '@/components/common/PermissionDenied'
import { hasCapability, type Capability } from '../capabilities'
import { useAuth } from '../hooks/useAuth'
import { LOGIN_REDIRECT_KEY } from '../hooks/useLoginForm'
import { LOGIN_PATH } from '../lib/destination'

interface ProtectedRouteProps {
  /** Lo que la ruta exige: una capacidad, nunca un nombre de rol. */
  capability: Capability
  children: ReactNode
}

/**
 * Deja pasar solo a quien tiene sesión y la capacidad que la ruta pide.
 *
 * Sin sesión (nunca la hubo, o expiró porque una petición recibió 401) manda al login recordando la
 * ruta actual en `LOGIN_REDIRECT_KEY`: al entrar de nuevo se vuelve a ella. Si la sesión expiró, el
 * login muestra «Su sesión expiró». Con sesión, quien no tiene la capacidad ve el aviso de permiso en
 * el mismo lugar: no se le cierra la sesión ni se le manda al login.
 *
 * No decide si el docente puede actuar sobre UN examen concreto (dueño, auxiliar habilitado o
 * invitado): eso es una política del backend.
 */
export function ProtectedRoute({ capability, children }: ProtectedRouteProps) {
  const { estado, rol } = useAuth()
  const location = useLocation()

  if (estado === 'anonimo' || estado === 'expirada') {
    return (
      <Navigate
        to={LOGIN_PATH}
        replace
        state={{ [LOGIN_REDIRECT_KEY]: `${location.pathname}${location.search}` }}
      />
    )
  }

  if (hasCapability(rol?.nombre_rol, capability)) {
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
