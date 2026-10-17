import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiClient } from '@/lib/api-client'

function stubFetch(response: Partial<Response> & { status: number }) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({ ok: response.status < 300, headers: new Headers(), ...response }) as Response)
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('apiClient con responseType blob', () => {
  it('devuelve el archivo como Blob y no intenta leerlo como JSON', async () => {
    const file = new Blob(['datos'], { type: 'image/png' })
    const json = vi.fn()
    stubFetch({ status: 200, blob: async () => file, json })

    await expect(apiClient<Blob>('/central-riesgo/1/evidencia', { responseType: 'blob' })).resolves.toBe(file)
    expect(json).not.toHaveBeenCalled()
  })

  it('sin responseType sigue leyendo JSON', async () => {
    const blob = vi.fn()
    stubFetch({ status: 200, json: async () => ({ data: 1 }), blob })

    await expect(apiClient('/materias')).resolves.toEqual({ data: 1 })
    expect(blob).not.toHaveBeenCalled()
  })

  it('un error de la descarga llega como ApiError con el mensaje del servidor', async () => {
    stubFetch({
      status: 404,
      json: async () => ({ message: 'El registro no tiene una evidencia disponible.' }),
    })

    const failure = apiClient('/central-riesgo/1/evidencia', { responseType: 'blob' })

    await expect(failure).rejects.toBeInstanceOf(ApiError)
    await expect(failure).rejects.toMatchObject({ status: 404, message: 'El registro no tiene una evidencia disponible.' })
  })
})
