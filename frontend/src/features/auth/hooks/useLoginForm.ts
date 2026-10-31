import {
  useCallback,
  useEffect,
  useState,
  type ChangeEvent,
  type FormEvent,
} from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { ApiError } from '@/lib/api-client'
import { resolveDestination, safeDestination } from '../lib/destination'
import { SIS_FORMAT_ERROR, cleanSis, isRecognisedSis } from '../lib/sis'
import type { LoginFailure } from '../types/auth.types'
import { useAuth } from './useAuth'
import { useCountdown } from './useCountdown'

/**
 * Toda la lógica del formulario de inicio de sesión (docs/design/rnf-02/17-login.html).
 *
 * LoginPage solo pinta lo que este hook entrega. La decisión de qué mostrar se toma con el estado y el
 * código `motivo` de la respuesta, nunca con el texto del mensaje.
 */

export const LOGIN_REDIRECT_KEY = 'from'

/**
 * Ids de los campos. El foco se mueve por id porque las primitivas de shadcn instaladas no reenvían
 * `ref` en React 18: es más simple y no depende de cómo se pinte cada campo.
 */
export const LOGIN_FIELD_IDS = { codSis: 'login-codigo-sis', password: 'login-password' } as const

export { safeDestination }

/** Espera que se asume cuando un 429 no trae `Retry-After`; coincide con la ventana de `throttle:5,1`. */
export const DEFAULT_RETRY_AFTER_SECONDS = 60

const THROTTLED_MESSAGE = 'Demasiados intentos. Espere un momento antes de reintentar.'
const UNAVAILABLE_MESSAGE = 'No se pudo conectar con el servidor. Puede reintentar.'

export const REQUIRED_SIS_ERROR = 'Ingrese su código SIS.'
export const REQUIRED_PASSWORD_ERROR = 'Ingrese su contraseña.'

export type LoginView = 'form' | 'inactive' | 'no-role' | 'success'

export interface LoginFieldErrors {
  codSis?: string
  password?: string
}

export interface UseLoginFormResult {
  codSis: string
  password: string
  onCodSisChange: (event: ChangeEvent<HTMLInputElement>) => void
  onPasswordChange: (event: ChangeEvent<HTMLInputElement>) => void
  /** Errores de validación del cliente; cada uno se borra cuando la persona escribe en su campo. */
  fieldErrors: LoginFieldErrors
  /** Los dos campos vacíos a la vez: el diseño suma un aviso general sobre el formulario. */
  bothEmpty: boolean
  /** Lo que muestra la tarjeta: el formulario, uno de los dos paneles de cuenta o el éxito. */
  view: LoginView
  isSubmitting: boolean
  /** Último fallo del servidor; `null` si no hubo o ya se resolvió. */
  failure: LoginFailure | null
  /** Segundos que faltan de un 429; 0 si no hay espera en curso. */
  throttleRemaining: number
  /** Campos en solo lectura: mientras se envía y durante la espera de un 429. */
  isReadOnly: boolean
  /** El código SIS con el que se intentó entrar, para los paneles de cuenta. */
  attemptedSis: string
  submit: (event?: FormEvent) => Promise<void>
  /** Reenvía exactamente lo escrito; es lo que ofrece «Reintentar» tras un fallo `indisponible`. */
  retry: () => Promise<void>
  /** Vuelve al formulario en reposo, con los campos vacíos («Usar otra cuenta», «Volver»). */
  reset: () => void
}

/**
 * Clasifica el fallo para que la pantalla elija cómo mostrarlo:
 * - petición no completada o 5xx: `indisponible`, antes que nada: no dice nada de la cuenta.
 * - 401: credenciales incorrectas (también cuando la cuenta no existe).
 * - 403: cuenta inactiva o sin rol vigente, según `motivo` (el texto del mensaje no se mira).
 * - 429: límite de intentos, con los segundos de `Retry-After`.
 */
export function toLoginFailure(error: unknown): LoginFailure {
  if (!(error instanceof ApiError)) {
    return { kind: 'desconocido', message: 'No se pudo iniciar sesión. Intente de nuevo.' }
  }

  if (error.isUnavailable) {
    return { kind: 'indisponible', message: UNAVAILABLE_MESSAGE }
  }

  if (error.isUnauthorized) {
    return { kind: 'credenciales', message: error.message }
  }

  if (error.isTooManyRequests) {
    return {
      kind: 'limitado',
      message: THROTTLED_MESSAGE,
      retryAfter: error.retryAfter ?? DEFAULT_RETRY_AFTER_SECONDS,
    }
  }

  if (error.isForbidden && error.motivo === 'cuenta_inactiva') {
    return { kind: 'cuenta-inactiva', message: error.message }
  }

  if (error.isForbidden && error.motivo === 'sin_rol_vigente') {
    return { kind: 'sin-rol', message: error.message }
  }

  return { kind: 'desconocido', message: error.message }
}

/** Lo que se puede saber sin el servidor: campos no vacíos y un código con forma reconocible. */
function validate(codSis: string, password: string): LoginFieldErrors {
  const errors: LoginFieldErrors = {}
  const sis = cleanSis(codSis)

  if (sis === '') {
    errors.codSis = REQUIRED_SIS_ERROR
  } else if (!isRecognisedSis(sis)) {
    errors.codSis = SIS_FORMAT_ERROR
  }

  if (password === '') {
    errors.password = REQUIRED_PASSWORD_ERROR
  }

  return errors
}

type FocusTarget = 'codSis' | 'password'

export function useLoginForm(): UseLoginFormResult {
  const { iniciarSesion } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [codSis, setCodSis] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<LoginFieldErrors>({})
  const [bothEmpty, setBothEmpty] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isSuccess, setIsSuccess] = useState(false)
  const [failure, setFailure] = useState<LoginFailure | null>(null)
  const [attemptedSis, setAttemptedSis] = useState('')
  const [throttleSeconds, setThrottleSeconds] = useState<number | null>(null)
  const [focusRequest, setFocusRequest] = useState<{ target: FocusTarget; nonce: number } | null>(null)

  const throttleRemaining = useCountdown(throttleSeconds)

  // Al llegar a cero la espera termina sola: los campos y el botón se reactivan.
  useEffect(() => {
    if (throttleSeconds !== null && throttleRemaining === 0) {
      setThrottleSeconds(null)
      setFailure(null)
    }
  }, [throttleRemaining, throttleSeconds])

  useEffect(() => {
    if (!focusRequest) return

    document.getElementById(LOGIN_FIELD_IDS[focusRequest.target])?.focus()
  }, [focusRequest])

  const requestFocus = useCallback((target: FocusTarget) => {
    setFocusRequest((previous) => ({ target, nonce: (previous?.nonce ?? 0) + 1 }))
  }, [])

  const isThrottled = throttleSeconds !== null && throttleRemaining > 0

  const submit = useCallback(
    async (event?: FormEvent) => {
      event?.preventDefault()

      // Un segundo envío mientras hay uno en curso, o durante la espera de un 429, no sale.
      if (isSubmitting || isSuccess || isThrottled) return

      const errors = validate(codSis, password)

      // Nada viaja al servidor hasta que esto pasa: un formulario vacío no gasta un intento del throttle.
      if (errors.codSis || errors.password) {
        setFieldErrors(errors)
        setBothEmpty(cleanSis(codSis) === '' && password === '')
        requestFocus(errors.codSis ? 'codSis' : 'password')

        return
      }

      const sis = cleanSis(codSis)

      setFieldErrors({})
      setBothEmpty(false)
      setFailure(null)
      setAttemptedSis(sis)
      setIsSubmitting(true)

      try {
        const role = await iniciarSesion(sis, password, {
          onCredentialsAccepted: () => setIsSuccess(true),
        })

        const state = location.state as Record<string, unknown> | null

        navigate(resolveDestination(state?.[LOGIN_REDIRECT_KEY], role.nombre_rol), { replace: true })
      } catch (error) {
        const nextFailure = toLoginFailure(error)

        setIsSuccess(false)
        setFailure(nextFailure)

        // Si la petición no se completó no hay nada que corregir: se conserva todo lo escrito,
        // contraseña incluida. En cualquier otro fallo la contraseña se vacía.
        if (nextFailure.kind !== 'indisponible') {
          setPassword('')
        }

        if (nextFailure.kind === 'credenciales') {
          requestFocus('password')
        }

        if (nextFailure.kind === 'limitado') {
          setThrottleSeconds(nextFailure.retryAfter ?? DEFAULT_RETRY_AFTER_SECONDS)
        }
      } finally {
        setIsSubmitting(false)
      }
    },
    [
      codSis,
      isSubmitting,
      isSuccess,
      isThrottled,
      iniciarSesion,
      location.state,
      navigate,
      password,
      requestFocus,
    ]
  )

  const retry = useCallback(() => submit(), [submit])

  const reset = useCallback(() => {
    setCodSis('')
    setPassword('')
    setFieldErrors({})
    setBothEmpty(false)
    setFailure(null)
    setAttemptedSis('')
    setThrottleSeconds(null)
    requestFocus('codSis')
  }, [requestFocus])

  // El error de un campo se borra al escribir en él, no al salir.
  const onCodSisChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    setCodSis(event.target.value)
    setFieldErrors((previous) => ({ ...previous, codSis: undefined }))
    setBothEmpty(false)
  }, [])

  const onPasswordChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    setPassword(event.target.value)
    setFieldErrors((previous) => ({ ...previous, password: undefined }))
    setBothEmpty(false)
  }, [])

  let view: LoginView = 'form'

  if (isSuccess) {
    view = 'success'
  } else if (failure?.kind === 'cuenta-inactiva') {
    view = 'inactive'
  } else if (failure?.kind === 'sin-rol') {
    view = 'no-role'
  }

  return {
    codSis,
    password,
    onCodSisChange,
    onPasswordChange,
    fieldErrors,
    bothEmpty,
    view,
    isSubmitting,
    failure,
    throttleRemaining: isThrottled ? throttleRemaining : 0,
    isReadOnly: isSubmitting || isThrottled,
    attemptedSis,
    submit,
    retry,
    reset,
  }
}
