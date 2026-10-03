import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import {
  ApiError,
  REQUEST_TIMEOUT_MS,
  apiClient,
  apiPost,
  configureSessionHandlers,
} from './api-client'

/**
 * El error que recibe el llamador debe traer todo lo que la pantalla necesita para decidir:
 * el estado HTTP, el código `motivo` del cuerpo y, en un 429, los segundos de `Retry-After`.
 * Y debe distinguir «la petición no se completó» de «el servidor respondió con un error».
 */
describe('api-client — el error trae estado, código y Retry-After', () => {
  afterEach(() => {
    configureSessionHandlers(null)
  })

  it('un 429 trae el estado y los segundos de Retry-After', async () => {
    mockApiWith(() => ({
      status: 429,
      body: { message: 'Too Many Attempts.' },
      headers: { 'Retry-After': '42' },
    }))

    const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(429)
    expect(error.retryAfter).toBe(42)
  })

  it('un 429 sin Retry-After no inventa un valor: lo decide la pantalla', async () => {
    mockApiWith(() => ({ status: 429, body: { message: 'Too Many Attempts.' } }))

    const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(error.retryAfter).toBeUndefined()
  })

  it('Retry-After como fecha HTTP se convierte a segundos', async () => {
    const inThirtySeconds = new Date(Date.now() + 30_000).toUTCString()

    mockApiWith(() => ({ status: 429, body: {}, headers: { 'Retry-After': inThirtySeconds } }))

    const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(error.retryAfter).toBeGreaterThanOrEqual(28)
    expect(error.retryAfter).toBeLessThanOrEqual(31)
  })

  it('los dos 403 del login traen su código `motivo` además del mensaje', async () => {
    const responses = [
      { message: 'Su cuenta está deshabilitada. Contacte al Administrador.', motivo: 'cuenta_inactiva' },
      { message: 'Su cuenta no tiene un rol vigente. Contacte al Administrador.', motivo: 'sin_rol_vigente' },
    ]

    for (const body of responses) {
      mockApiWith(() => ({ status: 403, body }))

      const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

      expect(error.status).toBe(403)
      expect(error.motivo).toBe(body.motivo)
      expect(error.message).toBe(body.message)
      expect(error.retryAfter).toBeUndefined()
    }
  })

  it('un 401 del login trae su código `motivo`', async () => {
    mockApiWith(() => ({
      status: 401,
      body: { message: 'Código SIS o contraseña incorrectos.', motivo: 'credenciales_invalidas' },
    }))

    const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(error.status).toBe(401)
    expect(error.motivo).toBe('credenciales_invalidas')
  })
})

describe('api-client — el 403 del login llega como dato', () => {
  const onSessionExpired = vi.fn()

  beforeEach(() => {
    onSessionExpired.mockClear()
    // Aun con una sesión abierta, el login no recibe tratamiento genérico.
    configureSessionHandlers({ getToken: () => 'tok-vigente', onSessionExpired })
  })

  afterEach(() => {
    configureSessionHandlers(null)
  })

  it('un 403 del login llega al llamador sin tocar la sesión ni llevar el token', async () => {
    const { calls } = mockApiWith(() => ({
      status: 403,
      body: { message: 'Su cuenta está deshabilitada.', motivo: 'cuenta_inactiva' },
    }))

    const error = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(error.isForbidden).toBe(true)
    expect(error.motivo).toBe('cuenta_inactiva')
    expect(onSessionExpired).not.toHaveBeenCalled()
    expect(calls[0].headers).not.toHaveProperty('Authorization')
  })

  it('fuera del login un 403 sigue sin cerrar la sesión y un 401 con token sigue dándola por muerta', async () => {
    mockApiWith(({ url }) =>
      url.endsWith('/ambientes')
        ? { status: 403, body: { message: 'Sin permiso.' } }
        : { status: 401, body: { message: 'Unauthenticated.' } }
    )

    await expect(apiClient('/ambientes')).rejects.toMatchObject({ status: 403 })
    expect(onSessionExpired).not.toHaveBeenCalled()

    await expect(apiClient('/examenes')).rejects.toMatchObject({ status: 401 })
    expect(onSessionExpired).toHaveBeenCalledTimes(1)
  })
})

describe('api-client — petición no completada frente a respuesta con error', () => {
  afterEach(() => {
    vi.useRealTimers()
    configureSessionHandlers(null)
  })

  /** Un `fetch` que no responde nunca, pero obedece a la cancelación como el real. */
  function stubHangingFetch(): void {
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (_url: unknown, init: RequestInit) =>
          new Promise((_resolve, reject) => {
            init.signal?.addEventListener('abort', () =>
              reject(new DOMException('The operation was aborted.', 'AbortError'))
            )
          })
      )
    )
  }

  it('sin respuesta en 15 s la petición termina con un error de tipo timeout', async () => {
    vi.useFakeTimers()
    stubHangingFetch()

    const pending = apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)

    await vi.advanceTimersByTimeAsync(REQUEST_TIMEOUT_MS - 1)
    // Aún dentro del plazo: no hay error todavía.
    let settled = false
    void pending.then(() => (settled = true))
    await Promise.resolve()
    expect(settled).toBe(false)

    await vi.advanceTimersByTimeAsync(1)

    const error = (await pending) as ApiError

    expect(REQUEST_TIMEOUT_MS).toBe(15_000)
    expect(error).toBeInstanceOf(ApiError)
    expect(error.kind).toBe('timeout')
    expect(error.status).toBe(0)
    expect(error.isTimeout).toBe(true)
    expect(error.isNetworkFailure).toBe(true)
  })

  it('un timeout no se confunde con un 401: no es un problema de credenciales', async () => {
    vi.useFakeTimers()
    stubHangingFetch()

    const pending = apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)
    await vi.advanceTimersByTimeAsync(REQUEST_TIMEOUT_MS)
    const timeout = (await pending) as ApiError

    mockApiWith(() => ({ status: 401, body: { message: 'x', motivo: 'credenciales_invalidas' } }))
    const unauthorized = (await apiPost('/auth/login', {}, { skipAuth: true }).catch((e: unknown) => e)) as ApiError

    expect(timeout.isUnauthorized).toBe(false)
    expect(timeout.motivo).toBeUndefined()
    expect(unauthorized.isUnauthorized).toBe(true)
    expect(unauthorized.isNetworkFailure).toBe(false)
    expect(unauthorized.kind).toBe('http')
  })

  it('una caída de red es un error de tipo network, sin estado HTTP', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new TypeError('Failed to fetch'))))

    const error = (await apiClient('/materias').catch((e: unknown) => e)) as ApiError

    expect(error.kind).toBe('network')
    expect(error.status).toBe(0)
    expect(error.isNetworkFailure).toBe(true)
    expect(error.isTimeout).toBe(false)
  })

  it('un 5xx es un error de tipo server: el servidor respondió, pero falló', async () => {
    mockApiWith(() => ({ status: 503, body: { message: 'Service Unavailable' } }))

    const error = (await apiClient('/materias').catch((e: unknown) => e)) as ApiError

    expect(error.kind).toBe('server')
    expect(error.isServerError).toBe(true)
    expect(error.isNetworkFailure).toBe(false)
    expect(error.isUnavailable).toBe(true)
  })

  it('un 4xx no cuenta como no disponible', async () => {
    mockApiWith(() => ({ status: 422, body: { message: 'x', errors: { a: ['b'] } } }))

    const error = (await apiClient('/materias').catch((e: unknown) => e)) as ApiError

    expect(error.kind).toBe('http')
    expect(error.isUnavailable).toBe(false)
  })

  it('la cancelación del llamador sigue propagándose como AbortError y no como timeout', async () => {
    stubHangingFetch()
    const controller = new AbortController()

    const pending = apiClient('/materias', { signal: controller.signal }).catch((e: unknown) => e)
    controller.abort()

    const error = await pending

    expect(error).toBeInstanceOf(DOMException)
    expect((error as DOMException).name).toBe('AbortError')
  })

  it('la respuesta a tiempo no deja el plazo corriendo', async () => {
    vi.useFakeTimers()
    mockApiWith(() => ({ body: { data: [] } }))

    await apiClient('/materias')

    expect(vi.getTimerCount()).toBe(0)
  })
})
