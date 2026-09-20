import { describe, expect, it } from 'vitest'
import { ApiError, apiClient } from '@/lib/api-client'
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
})
