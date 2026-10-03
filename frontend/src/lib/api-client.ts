import { env } from '@/config/env'
import type { ApiErrorBody } from '@/types/api.types'

/**
 * Cómo terminó una petición fallida:
 * - `network`: no hubo conexión; la petición no llegó o no volvió. `status` es 0.
 * - `timeout`: pasaron los 15 s sin respuesta. `status` es 0.
 * - `server`: el servidor respondió con un error 5xx.
 * - `http`: el servidor respondió con un error del cliente (4xx) que la vista interpreta.
 *
 * Una petición que no se completó (`network`, `timeout`) no dice nada sobre la cuenta ni sobre los
 * datos enviados: nunca debe presentarse como un problema de credenciales.
 */
export type ApiErrorKind = 'network' | 'timeout' | 'server' | 'http'

interface ApiErrorExtras {
  kind?: ApiErrorKind
  retryAfter?: number
}

/**
 * Error de una petición a la API, con el estado HTTP ya interpretado.
 *
 * Las vistas distinguen casos por `status` —403 sin permiso, 404 inexistente,
 * 422 regla de negocio— sin volver a leer el cuerpo de la respuesta. Para los rechazos de
 * autenticación llegan además el código estable `motivo` y, en un 429, los segundos de
 * `Retry-After`.
 */
export class ApiError extends Error {
  readonly status: number

  /** Si la petición se completó y de qué manera; ver `ApiErrorKind`. */
  readonly kind: ApiErrorKind

  /** Segundos que indica la cabecera `Retry-After`; solo en las respuestas que la traen (429). */
  readonly retryAfter?: number

  /** Campos rechazados en un 422; vacío en el resto de los errores. */
  readonly errors: Record<string, string[]>

  /** Código estable de los rechazos de autenticación (`cuenta_inactiva`, `sin_rol_vigente`…). */
  readonly motivo?: string

  constructor(
    status: number,
    message: string,
    errors: Record<string, string[]> = {},
    motivo?: string,
    extras: ApiErrorExtras = {}
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.motivo = motivo
    this.kind = extras.kind ?? defaultKind(status)
    this.retryAfter = extras.retryAfter
  }

  /** La petición nunca se completó: sin conexión o sin respuesta a tiempo. */
  get isNetworkFailure(): boolean {
    return this.kind === 'network' || this.kind === 'timeout'
  }

  get isTimeout(): boolean {
    return this.kind === 'timeout'
  }

  /** El servidor respondió, pero falló por su cuenta (5xx). */
  get isServerError(): boolean {
    return this.kind === 'server'
  }

  /** No se obtuvo una respuesta útil: la petición no se completó o el servidor falló. */
  get isUnavailable(): boolean {
    return this.isNetworkFailure || this.isServerError
  }

  get isUnauthorized(): boolean {
    return this.status === 401
  }

  get isTooManyRequests(): boolean {
    return this.status === 429
  }

  /** El recurso existe pero no es del docente. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  /** Regla de negocio rechazada, como el par materia-carrera inactivo o una duplicidad. */
  get isValidation(): boolean {
    return this.status === 422
  }
}

function defaultKind(status: number): ApiErrorKind {
  if (status === 0) return 'network'

  return status >= 500 ? 'server' : 'http'
}

/** Tiempo máximo de espera de una petición. */
export const REQUEST_TIMEOUT_MS = 15_000

const NETWORK_ERROR = 'No se pudo conectar con el servidor. Revise su conexión.'
const TIMEOUT_ERROR = 'El servidor tardó demasiado en responder. Intente de nuevo.'
const UNEXPECTED_ERROR = 'Ocurrió un error inesperado al consultar el servidor.'

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
type Query = Record<string, string | number | undefined>

/**
 * Lo que el cliente HTTP necesita saber de la sesión sin depender del feature `auth`:
 * el token a enviar y qué hacer cuando una petición con token recibe un 401.
 */
export interface SessionHandlers {
  getToken: () => string | null
  onSessionExpired: () => void
}

let sessionHandlers: SessionHandlers | null = null

/** El proveedor de sesión se registra aquí al montarse y se da de baja al desmontarse. */
export function configureSessionHandlers(handlers: SessionHandlers | null): void {
  sessionHandlers = handlers
}

export interface ApiClientOptions {
  method?: ApiMethod
  /**
   * Cuerpo de la petición. Se serializa como JSON, salvo que sea un `FormData`
   * —el caso de la carga de nómina—, que se envía tal cual. Ignorado en GET.
   */
  body?: unknown
  signal?: AbortSignal
  query?: Query
  /**
   * Tiempo máximo de espera en milisegundos; por defecto `REQUEST_TIMEOUT_MS` (15 s).
   * Al vencer, el error es de tipo `timeout`.
   */
  timeoutMs?: number
  /**
   * La petición no usa la sesión: no lleva token y NINGÚN rechazo recibe tratamiento genérico.
   * Es el caso del inicio de sesión, donde el 401 (credenciales incorrectas) y los dos 403
   * (cuenta inactiva, sin rol) son resultados que la pantalla muestra, no una sesión caída ni un
   * permiso negado: llegan al llamador como `ApiError` con su `status`, `motivo` y `retryAfter`.
   */
  skipAuth?: boolean
  /**
   * La petición lleva el token, pero un 401 lo resuelve quien la hace y no pone la app en
   * «sesión expirada» (rehidratar la sesión y cerrarla).
   */
  ignoreUnauthorized?: boolean
}

/**
 * Único punto de salida HTTP de la aplicación.
 *
 * Toda llamada a la API pasa por aquí: ningún feature usa `fetch` directamente.
 */
export async function apiClient<TResponse>(
  path: string,
  options: ApiClientOptions = {}
): Promise<TResponse> {
  const method = options.method ?? 'GET'
  const hasBody = options.body !== undefined && method !== 'GET'
  const url = buildUrl(path, options.query)
  const isFormData = options.body instanceof FormData

  const headers: Record<string, string> = { Accept: 'application/json' }

  const token = options.skipAuth ? null : sessionHandlers?.getToken() ?? null

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  /*
   * Un multipart lo arma el navegador, que es quien conoce el `boundary` con el
   * que separa las partes: fijar aquí el Content-Type dejaría el cuerpo ilegible
   * para el servidor.
   */
  if (hasBody && !isFormData) {
    headers['Content-Type'] = 'application/json'
  }

  /*
   * Un solo AbortController gobierna la petición: lo dispara el plazo de espera o la cancelación del
   * llamador. `timedOut` distingue las dos causas, que no son lo mismo: la cancelación se propaga
   * tal cual y el plazo vencido es un fallo que la vista debe poder mostrar.
   */
  const controller = new AbortController()
  let timedOut = false

  const timer = setTimeout(() => {
    timedOut = true
    controller.abort()
  }, options.timeoutMs ?? REQUEST_TIMEOUT_MS)

  const forwardCancellation = () => controller.abort()

  if (options.signal?.aborted) {
    controller.abort()
  } else {
    options.signal?.addEventListener('abort', forwardCancellation, { once: true })
  }

  const interrupted = (cause: unknown): unknown => {
    if (!(cause instanceof DOMException && cause.name === 'AbortError')) {
      return new ApiError(0, NETWORK_ERROR)
    }

    return timedOut ? new ApiError(0, TIMEOUT_ERROR, {}, undefined, { kind: 'timeout' }) : cause
  }

  try {
    let response: Response

    try {
      response = await fetch(url, {
        method,
        headers,
        body: toRequestBody(options.body),
        signal: controller.signal,
      })
    } catch (cause) {
      throw interrupted(cause)
    }

    if (!response.ok) {
      /*
       * Un 401 con token enviado es una sesión muerta. Sin token no lo es: sin sesión la app sigue
       * funcionando y algunas rutas ya responden 401 a los anónimos. Y las peticiones `skipAuth`
       * (el login) no llegan aquí con token: su 401 son credenciales incorrectas.
       */
      if (response.status === 401 && token && !options.ignoreUnauthorized) {
        sessionHandlers?.onSessionExpired()
      }

      throw await toApiError(response)
    }

    // Una respuesta sin cuerpo (ej. 204) no tiene JSON que parsear.
    if (response.status === 204) {
      return undefined as TResponse
    }

    try {
      return (await response.json()) as TResponse
    } catch (cause) {
      throw interrupted(cause)
    }
  } finally {
    clearTimeout(timer)
    options.signal?.removeEventListener('abort', forwardCancellation)
  }
}

/** Helpers directos para peticiones POST y PUT. */
export async function apiPost<TResponse>(
  path: string,
  body: unknown,
  options: Omit<ApiClientOptions, 'method' | 'body'> = {}
): Promise<TResponse> {
  return apiClient<TResponse>(path, { ...options, method: 'POST', body })
}

export async function apiPut<TResponse>(
  path: string,
  body: unknown,
  options: Omit<ApiClientOptions, 'method' | 'body'> = {}
): Promise<TResponse> {
  return apiClient<TResponse>(path, { ...options, method: 'PUT', body })
}

/** Un `FormData` viaja tal cual; cualquier otro cuerpo se serializa como JSON. */
function toRequestBody(body: unknown): BodyInit | undefined {
  if (body === undefined) return undefined
  if (body instanceof FormData) return body

  return JSON.stringify(body)
}

function buildUrl(path: string, query?: Query): string {
  const url = new URL(`${env.apiUrl}${path}`)

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined) {
      url.searchParams.set(key, String(value))
    }
  })

  return url.toString()
}

/**
 * El backend responde `{message, errors?}`, pero un 500 puede devolver HTML:
 * por eso el cuerpo se lee de forma tolerante y nunca se propaga el fallo de parseo.
 */
async function toApiError(response: Response): Promise<ApiError> {
  const body = (await response.json().catch(() => null)) as ApiErrorBody | null

  return new ApiError(
    response.status,
    body?.message ?? UNEXPECTED_ERROR,
    body?.errors ?? {},
    body?.motivo,
    { retryAfter: response.status === 429 ? parseRetryAfter(response.headers.get('Retry-After')) : undefined }
  )
}

/**
 * `Retry-After` llega en segundos enteros (así lo envía Laravel) o, según el estándar, como una
 * fecha HTTP. Se devuelve en segundos; sin cabecera o ilegible, `undefined` y decide la pantalla.
 */
function parseRetryAfter(value: string | null): number | undefined {
  if (!value) return undefined

  if (/^\d+$/.test(value.trim())) return Number(value)

  const date = Date.parse(value)

  return Number.isNaN(date) ? undefined : Math.max(0, Math.ceil((date - Date.now()) / 1000))
}
