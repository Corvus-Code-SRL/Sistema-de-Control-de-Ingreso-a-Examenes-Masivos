import { useContext } from 'react'
import { AuthContext, type AuthContextValue } from '../components/AuthProvider'

/** Sesión actual (`usuario`, `rol`, `token`, `estado`) y sus acciones. */
export function useAuth(): AuthContextValue {
  return useContext(AuthContext)
}
