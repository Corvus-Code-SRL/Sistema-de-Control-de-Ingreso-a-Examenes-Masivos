import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { makeLateEntryRequest, makeLateEntryVerification } from '@/test/lateEntryFixtures'
import { LATE_ENTRY_SEARCH, lateEntryService } from './lateEntryService'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('lateEntryService', () => {
  it('verifica con el SIS, el CI y el ambiente del auxiliar', async () => {
    const verification = makeLateEntryVerification()
    const { calls } = mockApiWith(() => ({ body: { data: verification } }))

    await expect(lateEntryService.verify(7, { cod_sis: '202301903', ci: '9342145', id_ambiente: 6 }))
      .resolves.toEqual(verification)

    expect(calls[0].method).toBe('POST')
    expect(calls[0].url).toContain('/control-ingreso/examenes/7/retrasos/verificar')
    expect(calls[0].body).toEqual({ cod_sis: '202301903', ci: '9342145', id_ambiente: 6 })
  })

  it('lista, solicita, autoriza, rechaza y registra en las rutas del examen', async () => {
    const request = makeLateEntryRequest()
    const registration = { id_estudiante: 501, estado_ingreso: 'CON_RETRASO', hora_ingreso: '10:30', id_ambiente: 5 }
    const { calls } = mockApiWith(({ method, url }) => {
      if (method === 'GET') return { body: { data: [request] } }
      if (url.endsWith('/registrar')) return { status: 201, body: { data: registration } }

      return { status: url.endsWith('/solicitudes') ? 201 : 200, body: { data: request } }
    })

    await expect(lateEntryService.list(7)).resolves.toEqual([request])
    await expect(lateEntryService.request(7, { id_estudiante: 501, id_ambiente: 6 })).resolves.toEqual(request)
    await expect(lateEntryService.approve(7, 31)).resolves.toEqual(request)
    await expect(lateEntryService.reject(7, 31)).resolves.toEqual(request)
    await expect(lateEntryService.register(7, { id_estudiante: 501, id_ambiente: 5 })).resolves.toEqual(registration)

    expect(calls.map(({ method, url }) => `${method} ${url.slice(url.indexOf('/control-ingreso'))}`)).toEqual([
      'GET /control-ingreso/examenes/7/retrasos/solicitudes',
      'POST /control-ingreso/examenes/7/retrasos/solicitudes',
      'POST /control-ingreso/examenes/7/retrasos/solicitudes/31/autorizar',
      'POST /control-ingreso/examenes/7/retrasos/solicitudes/31/rechazar',
      'POST /control-ingreso/examenes/7/retrasos/registrar',
    ])
  })

  it('entrega el mensaje del servidor cuando rechaza la operación', async () => {
    mockApiWith(() => ({
      status: 409,
      body: { message: 'Existe una solicitud pendiente para este estudiante.', veredicto: 'SOLICITUD_PENDIENTE' },
    }))

    await expect(lateEntryService.register(7, { id_estudiante: 501, id_ambiente: 5 }))
      .rejects.toMatchObject({ status: 409, message: 'Existe una solicitud pendiente para este estudiante.' })
  })

  it('busca por nombre o por prefijo en la ruta de HU-037, no en la de HU-032', async () => {
    const match = { id_estudiante: 501, cod_sis: '202301903', nombre_completo: 'Lucía Fernández Rojas' }
    const { calls } = mockApiWith(() => ({ body: { data: [match] } }))

    await expect(LATE_ENTRY_SEARCH.byName(7, 'lucia')).resolves.toEqual([match])
    await expect(LATE_ENTRY_SEARCH.byIdentifier(7, 'ci', '9342')).resolves.toEqual([match])

    expect(calls.map(({ url }) => url.slice(url.indexOf('/control-ingreso')))).toEqual([
      '/control-ingreso/examenes/7/retrasos/buscar?nombre=lucia',
      '/control-ingreso/examenes/7/retrasos/buscar?ci=9342',
    ])
  })
})
