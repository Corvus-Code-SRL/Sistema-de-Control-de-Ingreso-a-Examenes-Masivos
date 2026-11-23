import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import {
  acceptRectificationRequest,
  countPendingRectificationRequests,
  listRectificationRequests,
  rejectRectificationRequest,
} from './rectificationRequestsService'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('rectificationRequestsService', () => {
  it('lista la bandeja y traduce el total de pendientes', async () => {
    const api = mockApiWith(() => ({ body: { data: [], meta: { total_pendientes: 2 } } }))

    const inbox = await listRectificationRequests()

    expect(new URL(api.calls[0].url).pathname).toMatch(/\/solicitudes-rectificacion$/)
    expect(inbox).toEqual({ solicitudes: [], totalPendientes: 2 })
  })

  it('una respuesta sin `data` ni `meta` cuenta cero y no falla', async () => {
    mockApiWith(() => ({ body: {} }))

    expect(await listRectificationRequests()).toEqual({ solicitudes: [], totalPendientes: 0 })
  })

  it('el conteo usa su ruta dedicada y tolera una respuesta vacía', async () => {
    const api = mockApiWith(() => ({ body: { data: { total_pendientes: 4 } } }))

    expect(await countPendingRectificationRequests()).toBe(4)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/conteo$/)

    mockApiWith(() => ({ body: {} }))

    expect(await countPendingRectificationRequests()).toBe(0)
  })

  it('acepta con POST a /aceptar', async () => {
    const api = mockApiWith(() => ({ body: { data: { id_solicitud_rect: 9980 } } }))

    await acceptRectificationRequest(9980)

    expect(api.calls[0].method).toBe('POST')
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/9980\/aceptar$/)
  })

  it('rechaza como multipart con el motivo y la imagen, o solo el motivo', async () => {
    const api = mockApiWith(() => ({ body: { data: { id_solicitud_rect: 9980 } } }))
    const image = new File(['x'], 'prueba.png', { type: 'image/png' })

    await rejectRectificationRequest(9980, { motivo: 'Motivo suficiente para rechazar.', evidencia: image })
    await rejectRectificationRequest(9980, { motivo: 'Motivo suficiente para rechazar.', evidencia: null })

    const [withImage, withoutImage] = api.calls.map((call) => call.body as FormData)

    expect(new URL(api.calls[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/9980\/rechazar$/)
    expect(withImage).toBeInstanceOf(FormData)
    expect(withImage.get('motivo')).toBe('Motivo suficiente para rechazar.')
    expect((withImage.get('evidencia') as File).name).toBe('prueba.png')
    expect(withoutImage.has('evidencia')).toBe(false)
  })
})
