import { describe, expect, it, vi } from 'vitest'
import { ApiError, apiClient, apiPost, apiPut } from '@/lib/api-client'
import { mockApiOnce, mockNetworkFailure } from '@/test/http'

/** Ejecuta una petición que debe fallar y devuelve su ApiError ya tipado. */
async function captureApiError(request: Promise<unknown>): Promise<ApiError> {
  try {
    await request
  } catch (cause) {
    if (cause instanceof ApiError) {
      return cause
    }

    throw cause
  }

  throw new Error('Se esperaba que la petición fallara')
}

/** Argumentos con los que el cliente llamó a `fetch` en la última petición. */
function lastFetchCall(): { url: string; init: RequestInit } {
  const calls = vi.mocked(fetch).mock.calls
  const [input, init] = calls[calls.length - 1] ?? []

  return { url: String(input), init: init ?? {} }
}

describe('apiClient', () => {
  it('devuelve el cuerpo de una respuesta correcta', async () => {
    mockApiOnce({ body: { data: [{ id_materia: 1 }] } })

    await expect(apiClient('/materias')).resolves.toEqual({ data: [{ id_materia: 1 }] })
  })

  it('traduce un 422 conservando el mensaje y los campos rechazados', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'La materia seleccionada no está activa en esta carrera.',
        errors: { id_materia: ['La materia seleccionada no está activa en esta carrera.'] },
      },
    })

    const error = await captureApiError(apiClient('/materias'))

    expect(error.isValidation).toBe(true)
    expect(error.message).toBe('La materia seleccionada no está activa en esta carrera.')
    expect(error.errors.id_materia).toHaveLength(1)
  })

  it('distingue un 404 de un 403', async () => {
    mockApiOnce({ status: 404, body: { message: 'No existe el grupo indicado.' } })

    const notFound = await captureApiError(apiClient('/grupos/1'))

    expect(notFound.isNotFound).toBe(true)
    expect(notFound.isForbidden).toBe(false)

    mockApiOnce({ status: 403, body: { message: 'No autorizado.' } })

    const forbidden = await captureApiError(apiClient('/grupos/1'))

    expect(forbidden.isForbidden).toBe(true)
    expect(forbidden.isNotFound).toBe(false)
  })

  it('informa de la caída de red sin dejar escapar el error original', async () => {
    mockNetworkFailure()

    const error = await captureApiError(apiClient('/materias'))

    expect(error.status).toBe(0)
    expect(error.message).toMatch(/no se pudo conectar/i)
  })

  it('usa un mensaje propio cuando el servidor no devuelve JSON', async () => {
    mockApiOnce({ status: 500, invalidJson: true })

    const error = await captureApiError(apiClient('/materias'))

    expect(error.status).toBe(500)
    expect(error.message).toMatch(/error inesperado/i)
  })

  it('una consulta no envía cuerpo ni Content-Type', async () => {
    mockApiOnce({ body: { data: [] } })

    await apiClient('/materias')

    const { init } = lastFetchCall()
    expect(init.method).toBe('GET')
    expect(init.body).toBeUndefined()
    expect(init.headers).not.toHaveProperty('Content-Type')
  })
})

describe('apiPost', () => {
  it('envía el cuerpo como JSON y devuelve la respuesta', async () => {
    mockApiOnce({ body: { data: { id_rol: 2 }, mensaje: 'Rol asignado correctamente.' } })

    await expect(apiPost('/usuarios/abc/rol', { id_rol: 2 })).resolves.toEqual({
      data: { id_rol: 2 },
      mensaje: 'Rol asignado correctamente.',
    })

    const { url, init } = lastFetchCall()
    expect(url).toMatch(/\/usuarios\/abc\/rol$/)
    expect(init.method).toBe('POST')
    expect(init.body).toBe(JSON.stringify({ id_rol: 2 }))
    expect(init.headers).toMatchObject({
      Accept: 'application/json',
      'Content-Type': 'application/json',
    })
  })

  it('traduce un 422 con los campos rechazados', async () => {
    mockApiOnce({
      status: 422,
      body: { message: 'Debe seleccionar un rol.', errors: { id_rol: ['Debe seleccionar un rol.'] } },
    })

    const error = await captureApiError(apiPost('/usuarios/abc/rol', {}))

    expect(error.isValidation).toBe(true)
    expect(error.message).toBe('Debe seleccionar un rol.')
    expect(error.errors.id_rol).toEqual(['Debe seleccionar un rol.'])
  })

  it('traduce un 403 conservando el mensaje del servidor', async () => {
    mockApiOnce({ status: 403, body: { message: 'No puede modificar su propio rol.' } })

    const error = await captureApiError(apiPost('/usuarios/abc/rol', { id_rol: 1 }))

    expect(error.isForbidden).toBe(true)
    expect(error.message).toBe('No puede modificar su propio rol.')
  })
})

describe('apiPut', () => {
  it('envía el cuerpo como JSON con el método PUT', async () => {
    mockApiOnce({ body: { data: { ok: true } } })

    await expect(apiPut('/recurso/1', { nombre: 'Nuevo' })).resolves.toEqual({ data: { ok: true } })

    const { init } = lastFetchCall()
    expect(init.method).toBe('PUT')
    expect(init.body).toBe(JSON.stringify({ nombre: 'Nuevo' }))
  })

  it('informa de la caída de red', async () => {
    mockNetworkFailure()

    const error = await captureApiError(apiPut('/recurso/1', {}))

    expect(error.status).toBe(0)
    expect(error.message).toMatch(/no se pudo conectar/i)
  })
})
