import { useContext } from 'react'
import { CurrentUserContext, type CurrentUserContextValue } from '../components/CurrentUserProvider'

/**
 * Usuario conectado y su área de trabajo.
 *
 * Es el único punto por el que el resto de la aplicación pregunta quién está
 * usando SCIEM. Hoy lo responde un sustituto de desarrollo; mañana, la sesión.
 */
export function useCurrentUser(): CurrentUserContextValue {
  return useContext(CurrentUserContext)
}
