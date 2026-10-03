import { createContext, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { ApiError, configureSessionHandlers } from '@/lib/api-client'
import { confirmPassword, getSession, login, logout } from '../services/authService'
import { clearStoredToken, readStoredToken, storeToken } from '../services/tokenStorage'
import type { AuthState, SessionRole } from '../types/auth.types'

/**
 * Sesión de la aplicación (RNF-02, fase 2a).
 *
 * La sesión es ADITIVA: sin ella la app funciona exactamente como antes, atribuida al usuario
 * fijo del backend. Nada aquí obliga a iniciar sesión; eso llega con la fase 3.
 *
 * Estados (`estado`):
 * - `verificando`: hay un token guardado y se comprueba con GET /auth/yo antes de pintar las
 *   rutas, para no mostrar un instante la pantalla equivocada en cada recarga.
 * - `anonimo`: sin sesión.
 * - `autenticado`: sesión vigente.
 * - `expirada`: una petición con token recibió 401. El token ya no sirve y la pantalla se
 *   bloquea (SessionExpiredOverlay) hasta que el usuario vuelva a entrar.
 */

export interface IniciarSesionOptions {
  /** Se llama cuando el servidor aceptó las credenciales, antes de pedir el rol a GET /auth/yo. */
  onCredentialsAccepted?: () => void
}

export interface AuthContextValue extends AuthState {
  /**
   * Inicia sesión y devuelve el rol vigente, que sale de GET /auth/yo y nunca del código SIS.
   * Rechaza con `ApiError` si el servidor no acepta las credenciales o si /yo falla.
   */
  iniciarSesion: (
    codSis: string,
    password: string,
    options?: IniciarSesionOptions
  ) => Promise<SessionRole>
  /** Cierra la sesión del servidor y la local; si el servidor falla, la local se cierra igual. */
  cerrarSesion: () => Promise<void>
  /** Rechaza con `ApiError` (422) si la contraseña es incorrecta. */
  confirmarPassword: (password: string) => Promise<void>
}

const ANONYMOUS: AuthState = { usuario: null, rol: null, token: null, estado: 'anonimo' }

/**
 * El valor por defecto deja montar pantallas fuera del proveedor, que es lo que hacen las
 * pruebas de las páginas: se comportan como una app sin sesión.
 */
export const AuthContext = createContext<AuthContextValue>({
  ...ANONYMOUS,
  iniciarSesion: () => Promise.reject(new Error('AuthProvider no está montado.')),
  cerrarSesion: () => Promise.resolve(),
  confirmarPassword: () => Promise.reject(new Error('AuthProvider no está montado.')),
})

function initialState(): AuthState {
  const token = readStoredToken()

  return token ? { ...ANONYMOUS, token, estado: 'verificando' } : ANONYMOUS
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>(initialState)

  // El cliente HTTP lee el token de aquí, no del estado, para no depender de un render.
  const tokenRef = useRef<string | null>(state.token)

  const apply = useCallback((next: AuthState) => {
    tokenRef.current = next.token
    setState(next)
  }, [])

  const expire = useCallback(() => {
    if (tokenRef.current === null) return

    clearStoredToken()
    tokenRef.current = null
    setState((previous) => ({ ...previous, token: null, estado: 'expirada' }))
  }, [])

  const handlers = useRef({ getToken: () => tokenRef.current, onSessionExpired: expire })

  /*
   * Se registra al renderizar por primera vez (los hijos montan y piden datos antes que los
   * efectos de este componente) y otra vez en el efecto, por si StrictMode lo dio de baja.
   */
  useState(() => configureSessionHandlers(handlers.current))

  useEffect(() => {
    configureSessionHandlers(handlers.current)

    return () => configureSessionHandlers(null)
  }, [])

  useEffect(() => {
    const token = readStoredToken()

    if (!token) return

    const controller = new AbortController()

    getSession(controller.signal)
      .then(({ data }) => {
        apply({ usuario: data.usuario, rol: data.rol, token, estado: 'autenticado' })
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return

        // Un token vencido o una cuenta que ya no puede entrar no son culpa del usuario: se
        // descarta sin mensaje. Un fallo de red o un 500 no prueba nada: el token se conserva.
        if (error instanceof ApiError && (error.isUnauthorized || error.isForbidden)) {
          clearStoredToken()
        }

        apply(ANONYMOUS)
      })

    return () => controller.abort()
  }, [apply])

  const iniciarSesion = useCallback(
    async (codSis: string, password: string, options?: IniciarSesionOptions) => {
      const { data: credentials } = await login(codSis, password)

      /*
       * El token se guarda ANTES de pedir /yo: esa petición tiene que llevarlo. La sesión solo pasa a
       * `autenticado` cuando /yo responde, porque el rol y la navegación salen de ahí; si /yo falla,
       * no queda nada a medias y la pantalla vuelve al formulario con el error.
       */
      storeToken(credentials.token)
      tokenRef.current = credentials.token
      options?.onCredentialsAccepted?.()

      try {
        const { data: session } = await getSession()

        apply({
          usuario: session.usuario,
          rol: session.rol,
          token: credentials.token,
          estado: 'autenticado',
        })

        return session.rol
      } catch (error) {
        clearStoredToken()
        tokenRef.current = null

        throw error
      }
    },
    [apply]
  )

  const cerrarSesion = useCallback(async () => {
    try {
      await logout()
    } catch {
      // El token puede estar ya muerto o no haber red: la sesión local se cierra de todos modos.
    } finally {
      clearStoredToken()
      apply(ANONYMOUS)
    }
  }, [apply])

  const confirmarPassword = useCallback(async (password: string) => {
    await confirmPassword(password)
  }, [])

  const value = useMemo(
    () => ({ ...state, iniciarSesion, cerrarSesion, confirmarPassword }),
    [state, iniciarSesion, cerrarSesion, confirmarPassword]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
