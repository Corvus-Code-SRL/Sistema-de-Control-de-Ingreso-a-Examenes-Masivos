import { useMemo } from 'react'
import { areaForRole, capabilitiesOf, type Capability } from '../capabilities'
import type { Area, CurrentUser } from '../types/auth.types'
import { useAuth } from './useAuth'

export interface CurrentUserValue {
  user: CurrentUser
  area: Area
  /** Lo que el rol de la sesión permite hacer; vacío sin sesión o con un rol desconocido. */
  capabilities: readonly Capability[]
}

/** Qué se muestra mientras no hay sesión (el instante previo a la redirección al login). */
const ANONYMOUS_USER: CurrentUser = { nombre: 'Sin sesión', iniciales: '?', area: 'docente' }

/**
 * Usuario conectado y su área de trabajo, derivados de la sesión.
 *
 * Es el punto por el que el layout y el router preguntan quién usa SCIEM. El área y las
 * capacidades salen del rol: Administrador → área Administrador; Docente y Auxiliar → área
 * Docente. Sin sesión el área es Docente y no hay capacidades.
 */
export function useCurrentUser(): CurrentUserValue {
  const { usuario, rol } = useAuth()

  return useMemo(() => {
    const area = areaForRole(rol?.nombre_rol)

    if (!usuario) {
      return { user: ANONYMOUS_USER, area, capabilities: [] }
    }

    const iniciales = `${usuario.nombre.charAt(0)}${usuario.apellido_paterno.charAt(0)}`.toUpperCase()

    return {
      user: { nombre: usuario.nombre_completo, iniciales, area },
      area,
      capabilities: capabilitiesOf(rol?.nombre_rol),
    }
  }, [usuario, rol])
}
