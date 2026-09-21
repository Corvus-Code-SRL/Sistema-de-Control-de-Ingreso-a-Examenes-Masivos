import { env } from '@/config/env'
import type { ApiErrorBody } from '@/types/api.types'

/**
 * Error de una petición a la API, con el estado HTTP ya interpretado.
 *
 * Las vistas distinguen casos por `status` —403 sin permiso, 404 inexistente,
 * 422 regla de negocio— sin volver a leer el cuerpo de la respuesta.
 */
export class ApiError extends Error {
  readonly status: number

  /** Campos rechazados en un 422; vacío en el resto de los errores. */
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** El recurso existe pero no es del docente. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  /** Regla de negocio rechazada, como el par materia-carrera inactivo. */
  get isValidation(): boolean {
    return this.status === 422
  }
}

const NETWORK_ERROR = 'No se pudo conectar con el servidor. Revise su conexión.'
const UNEXPECTED_ERROR = 'Ocurrió un error inesperado al consultar el servidor.'

type Query = Record<string, string | number | undefined>

interface RequestOptions {
  signal?: AbortSignal
  query?: Query
}

/**
 * Único punto de salida HTTP de la aplicación.
 *
 * Toda llamada a la API pasa por aquí: ningún feature usa `fetch` directamente.
 */
export async function apiClient<TResponse>(
  path: string,
  options: RequestOptions = {}
): Promise<TResponse> {
  return request<TResponse>('GET', path, options)
}

/** Envía `body` como JSON; los errores se traducen igual que en una consulta. */
export async function apiPost<TResponse>(
  path: string,
  body: unknown,
  options: RequestOptions = {}
): Promise<TResponse> {
  return request<TResponse>('POST', path, options, body)
}

export async function apiPut<TResponse>(
  path: string,
  body: unknown,
  options: RequestOptions = {}
): Promise<TResponse> {
  return request<TResponse>('PUT', path, options, body)
}

async function request<TResponse>(
  method: 'GET' | 'POST' | 'PUT',
  path: string,
  options: RequestOptions,
  body?: unknown
): Promise<TResponse> {
  const url = buildUrl(path, options.query)

  const headers: Record<string, string> = { Accept: 'application/json' }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let response: Response

  try {
    response = await fetch(url, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: options.signal,
    })
  } catch (cause) {
    // Una cancelación se propaga tal cual: no es un fallo que la vista deba mostrar.
    if (cause instanceof DOMException && cause.name === 'AbortError') {
      throw cause
    }

    throw new ApiError(0, NETWORK_ERROR)
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  return (await response.json()) as TResponse
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
    body?.errors ?? {}
  )
}
