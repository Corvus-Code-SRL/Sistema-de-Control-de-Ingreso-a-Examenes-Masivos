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

  /** Regla de negocio rechazada, como el par materia-carrera inactivo o una duplicidad. */
  get isValidation(): boolean {
    return this.status === 422
  }
}

const NETWORK_ERROR = 'No se pudo conectar con el servidor. Revise su conexión.'
const UNEXPECTED_ERROR = 'Ocurrió un error inesperado al consultar el servidor.'

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export interface ApiClientOptions {
  method?: ApiMethod
  /** Cuerpo de la petición; se serializa como JSON. Ignorado en GET. */
  body?: unknown
  signal?: AbortSignal
  query?: Record<string, string | number | undefined>
}

/**
 * Único punto de salida HTTP de la aplicación.
 *
 * Toda llamada a la API pasa por aquí: ningún feature usa `fetch` directamente.
 *
 * Se agregó `method` y `body` para las primeras operaciones de escritura (registrar/actualizar grupo);
 * el manejo de errores no cambió. No incluye envío de cookie/CSRF ni cabecera de
 * autorización porque, según sciem.php, la autenticación real todavía no está
 * implementada — revisar esto cuando exista esa historia.
 */
export async function apiClient<TResponse>(
  path: string,
  options: ApiClientOptions = {}
): Promise<TResponse> {
  const url = buildUrl(path, options.query)
  const method = options.method ?? 'GET'
  const hasBody = options.body !== undefined && method !== 'GET'

  let response: Response

  try {
    response = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        ...(hasBody ? { 'Content-Type': 'application/json' } : {}),
      },
      body: hasBody ? JSON.stringify(options.body) : undefined,
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

  // Una respuesta sin cuerpo (ej. 204) no tiene JSON que parsear.
  if (response.status === 204) {
    return undefined as TResponse
  }

  return (await response.json()) as TResponse
}

function buildUrl(path: string, query?: Record<string, string | number | undefined>): string {
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