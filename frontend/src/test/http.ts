import { vi } from 'vitest'

/**
 * Sustituye `fetch` para que las pruebas describan respuestas de la API.
 *
 * Se intercepta la red y no `api-client`, de modo que el cliente HTTP real
 * —incluida su traducción de errores— queda cubierto por las mismas pruebas.
 */

interface StubbedResponse {
  status?: number
  body?: unknown
  /** El cuerpo no es JSON, como en un 500 que devuelve HTML. */
  invalidJson?: boolean
}

type RouteMatcher = (url: string) => boolean

interface Route extends StubbedResponse {
  matches: RouteMatcher
}

/** Responde según la ruta pedida; una petición sin ruta declarada falla la prueba. */
export function mockApi(routes: Route[]): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      const route = routes.find((candidate) => candidate.matches(url))

      if (!route) {
        throw new Error(`Petición no esperada en la prueba: ${url}`)
      }

      return jsonResponse(route)
    })
  )
}

/** Toda petición responde lo mismo; sirve cuando la prueba solo mira un endpoint. */
export function mockApiOnce(response: StubbedResponse): void {
  mockApi([{ matches: () => true, ...response }])
}

/** Simula una caída de red, que `api-client` traduce a un ApiError sin estado. */
export function mockNetworkFailure(): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => {
      throw new TypeError('Failed to fetch')
    })
  )
}

export const matchers = {
  catalog: (url: string) => url.includes('/materias') && !url.includes('/carreras/'),
  groupsOfPair: (url: string) => /\/carreras\/\d+\/materias\/\d+\/grupos/.test(url),
  groupDetail: (url: string) => /\/grupos\/\d+$/.test(url),
  rosterPreview: (url: string) => /\/grupos\/\d+\/nomina\/preview$/.test(url),
  rosterConfirm: (url: string) => /\/grupos\/\d+\/nomina\/confirm$/.test(url),
}

function jsonResponse({ status = 200, body = {}, invalidJson = false }: StubbedResponse): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => {
      if (invalidJson) {
        throw new SyntaxError('Unexpected token < in JSON at position 0')
      }

      return body
    },
  } as Response
}


export interface RecordedRequest {
  url: string
  method: string
  headers: Record<string, string>
  body: unknown
}

/**
 * Responde según la petición completa (método, cabeceras y cuerpo), no solo la URL.
 *
 * Hace falta para probar la sesión: el mismo endpoint responde distinto según el método y las
 * pruebas miran si viajó la cabecera Authorization. Devuelve el `fetch` simulado para inspeccionar
 * las llamadas. Una petición que el manejador no resuelve (devuelve `undefined`) falla la prueba.
 */
export function mockApiWith(
  handler: (request: RecordedRequest) => StubbedResponse | undefined
): { calls: RecordedRequest[] } {
  const calls: RecordedRequest[] = []

  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
      const request: RecordedRequest = {
        url: String(input),
        method: init.method ?? 'GET',
        headers: (init.headers ?? {}) as Record<string, string>,
        body: typeof init.body === 'string' ? JSON.parse(init.body) : init.body,
      }

      calls.push(request)

      const response = handler(request)

      if (!response) {
        throw new Error(`Petición no esperada en la prueba: ${request.method} ${request.url}`)
      }

      return jsonResponse(response)
    })
  )

  return { calls }
}
