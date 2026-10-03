import { useCallback, useEffect, useRef, useState, type FormEvent, type RefObject } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { ApiError } from '@/lib/api-client'
import type { LoginFailure } from '../types/auth.types'
import { useAuth } from './useAuth'

/**
 * Toda la lógica del formulario de inicio de sesión.
 *
 * La pantalla solo pinta lo que este hook entrega: cuando la fase 2b cambie el diseño de
 * LoginPage, esta lógica no se toca.
 */

export const LOGIN_REDIRECT_KEY = 'from'

/** Mismo texto para credenciales incorrectas y para el límite de intentos (429). */
const CREDENTIALS_MESSAGE = 'Código SIS o contraseña incorrectos.'

export interface UseLoginFormResult {
  codSis: string
  setCodSis: (value: string) => void
  password: string
  setPassword: (value: string) => void
  isSubmitting: boolean
  /** `null` mientras no haya un intento fallido. */
  failure: LoginFailure | null
  /** Referencia del campo de contraseña: tras un fallo recibe el foco. */
  passwordRef: RefObject<HTMLInputElement>
  submit: (event: FormEvent) => Promise<void>
}

/**
 * Clasifica el fallo para que la pantalla elija cómo mostrarlo:
 * - 401: credenciales incorrectas (también cuando la cuenta no existe).
 * - 403: cuenta inactiva o sin rol vigente, según `motivo`.
 * - 429: límite de intentos; se trata como credenciales incorrectas, con el mismo texto.
 */
export function toLoginFailure(error: unknown): LoginFailure {
  if (!(error instanceof ApiError)) {
    return { kind: 'desconocido', message: 'No se pudo iniciar sesión. Intente de nuevo.' }
  }

  if (error.isUnauthorized) {
    return { kind: 'credenciales', message: error.message }
  }

  if (error.isTooManyRequests) {
    return { kind: 'limitado', message: CREDENTIALS_MESSAGE }
  }

  if (error.isForbidden && error.motivo === 'cuenta_inactiva') {
    return { kind: 'cuenta-inactiva', message: error.message }
  }

  if (error.isForbidden && error.motivo === 'sin_rol_vigente') {
    return { kind: 'sin-rol', message: error.message }
  }

  if (error.status === 0) {
    return { kind: 'red', message: error.message }
  }

  return { kind: 'desconocido', message: error.message }
}

/** Solo se vuelve a rutas internas distintas del propio login. */
export function safeDestination(from: unknown): string {
  return typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') && !from.startsWith('/login')
    ? from
    : '/'
}

export function useLoginForm(): UseLoginFormResult {
  const { iniciarSesion } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [codSis, setCodSis] = useState('')
  const [password, setPassword] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [failure, setFailure] = useState<LoginFailure | null>(null)
  const [failureCount, setFailureCount] = useState(0)
  const passwordRef = useRef<HTMLInputElement>(null)

  // Cada fallo devuelve el foco a la contraseña, que es lo único que hay que reescribir.
  useEffect(() => {
    if (failureCount > 0) {
      passwordRef.current?.focus()
    }
  }, [failureCount])

  const submit = useCallback(
    async (event: FormEvent) => {
      event.preventDefault()

      if (isSubmitting) return

      setIsSubmitting(true)
      setFailure(null)

      try {
        // El código se envía tal como se escribió: el backend lo normaliza.
        await iniciarSesion(codSis, password)

        const state = location.state as Record<string, unknown> | null

        navigate(safeDestination(state?.[LOGIN_REDIRECT_KEY]), { replace: true })
      } catch (error) {
        setFailure(toLoginFailure(error))
        setPassword('')
        setFailureCount((count) => count + 1)
      } finally {
        setIsSubmitting(false)
      }
    },
    [codSis, isSubmitting, iniciarSesion, location.state, navigate, password]
  )

  return { codSis, setCodSis, password, setPassword, isSubmitting, failure, passwordRef, submit }
}
