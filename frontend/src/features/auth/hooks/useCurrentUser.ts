import { useMemo } from 'react'
import { areaForRole } from '../capabilities'
import type { Area, CurrentUser } from '../types/auth.types'
import { useAuth } from './useAuth'

export interface CurrentUserValue {
  user: CurrentUser
  area: Area
}

/** Qué se muestra mientras no hay sesión: la app sigue operando como el usuario fijo del backend. */
const ANONYMOUS_USER: CurrentUser = { nombre: 'Sin sesión', iniciales: '?', area: 'docente' }

/**
 * Usuario conectado y su área de trabajo, derivados de la sesión.
 *
 * Es el punto por el que el layout y el router preguntan quién usa SCIEM. El área sale del rol:
 * Administrador → área Administrador; Docente y Auxiliar → área Docente. Sin sesión es Docente.
 */
export function useCurrentUser(): CurrentUserValue {
  const { usuario, rol } = useAuth()

  return useMemo(() => {
    const area = areaForRole(rol?.nombre_rol)

    if (!usuario) {
      return { user: ANONYMOUS_USER, area }
    }

    const iniciales = `${usuario.nombre.charAt(0)}${usuario.apellido_paterno.charAt(0)}`.toUpperCase()

    return { user: { nombre: usuario.nombre_completo, iniciales, area }, area }
  }, [usuario, rol])
}
