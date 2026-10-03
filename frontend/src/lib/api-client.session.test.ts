import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { ApiError, apiClient, apiPost, configureSessionHandlers } from './api-client'

/**
 * El cliente HTTP y la sesión: cada código de respuesta es una situación distinta y confundirlas
 * produce los fallos reales (cerrar la sesión por un 403, bloquear la pantalla por credenciales
 * incorrectas, tratar de «sesión muerta» a un anónimo).
 */
describe('api-client — sesión', () => {
  const onSessionExpired = vi.fn()
  let token: string | null

  beforeEach(() => {
    token = 'abc123'
    onSessionExpired.mockClear()
    configureSessionHandlers({ getToken: () => token, onSessionExpired })
  })

  afterEach(() => {
    configureSessionHandlers(null)
  })

  it('adjunta el token como Bearer cuando hay sesión', async () => {
    const { calls } = mockApiWith(() => ({ body: { data: [] } }))

    await apiClient('/materias')

    expect(calls[0].headers.Authorization).toBe('Bearer abc123')
  })

  it('no envía Authorization cuando no hay sesión', async () => {
    token = null
    const { calls } = mockApiWith(() => ({ body: { data: [] } }))

    await apiClient('/materias')

    expect(calls[0].headers).not.toHaveProperty('Authorization')
  })

  it('un 401 del login no limpia la sesión ni la da por muerta: son credenciales incorrectas', async () => {
    const { calls } = mockApiWith(() => ({
      status: 401,
      body: { message: 'Código SIS o contraseña incorrectos.', motivo: 'credenciales_invalidas' },
    }))

    const error = await apiPost('/auth/login', { cod_sis: '1', password: 'x' }, { skipAuth: true }).catch(
      (cause: unknown) => cause
    )

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(401)
    expect((error as ApiError).motivo).toBe('credenciales_invalidas')
    expect(onSessionExpired).not.toHaveBeenCalled()
    // Ni siquiera lleva el token de una sesión que pudiera existir.
    expect(calls[0].headers).not.toHaveProperty('Authorization')
  })

  it('un 401 en cualquier otro endpoint con token da la sesión por muerta', async () => {
    mockApiWith(() => ({ status: 401, body: { message: 'Unauthenticated.' } }))

    const error = await apiClient('/examenes').catch((cause: unknown) => cause)

    expect((error as ApiError).isUnauthorized).toBe(true)
    expect(onSessionExpired).toHaveBeenCalledTimes(1)
  })

  it('un 401 sin token no es una sesión muerta: sin sesión la app sigue funcionando', async () => {
    token = null
    mockApiWith(() => ({ status: 401, body: { message: 'Unauthenticated.' } }))

    await expect(apiClient('/materias/administracion')).rejects.toMatchObject({ status: 401 })

    expect(onSessionExpired).not.toHaveBeenCalled()
  })

  it('rehidratar o cerrar sesión pueden resolver su propio 401 sin bloquear la pantalla', async () => {
    mockApiWith(() => ({ status: 401, body: { message: 'Unauthenticated.' } }))

    await expect(apiClient('/auth/yo', { ignoreUnauthorized: true })).rejects.toMatchObject({
      status: 401,
    })

    expect(onSessionExpired).not.toHaveBeenCalled()
  })

  it('un 403 es un permiso negado en ese recurso: no toca la sesión', async () => {
    mockApiWith(() => ({ status: 403, body: { message: 'Solo un Administrador puede registrar ambientes.' } }))

    const error = await apiClient('/ambientes').catch((cause: unknown) => cause)

    expect((error as ApiError).isForbidden).toBe(true)
    expect(onSessionExpired).not.toHaveBeenCalled()
  })

  it('un 429 no pone la app en estado de sesión expirada', async () => {
    mockApiWith(() => ({ status: 429, body: { message: 'Too Many Attempts.' } }))

    const error = await apiPost('/auth/login', {}, { skipAuth: true }).catch((cause: unknown) => cause)

    expect((error as ApiError).isTooManyRequests).toBe(true)
    expect(onSessionExpired).not.toHaveBeenCalled()
  })
})
