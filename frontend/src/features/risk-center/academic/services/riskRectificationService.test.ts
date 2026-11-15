import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { carlaCondori, initialDetails, rectificationTypes } from '@/test/riskRectificationFixtures'
import {
  getRectifiableRecord,
  getRectificationFaultTypes,
  getStudentRecords,
  rectifyRecord,
  searchRectifiableStudents,
} from './riskRectificationService'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('riskRectificationService', () => {
  it('busca estudiantes con el dato en `q` y traduce el límite y la marca de más coincidencias', async () => {
    const api = mockApiWith(() => ({
      body: { data: [carlaCondori], meta: { limite: 10, hay_mas: true } },
    }))

    const result = await searchRectifiableStudents('condori')
    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/rectificacion-riesgo\/estudiantes$/)
    expect(Object.fromEntries(url.searchParams)).toEqual({ q: 'condori' })
    expect(result).toEqual({ students: [carlaCondori], limit: 10, hasMore: true })
  })

  it('pide los registros de un estudiante a su propia ruta, no a la consulta de la Central', async () => {
    const api = mockApiWith(() => ({ body: { data: { estudiante: carlaCondori, registros: [] } } }))

    const records = await getStudentRecords(9702)

    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/estudiantes\/9702\/registros$/)
    expect(api.calls[0].url).not.toContain('/central-riesgo')
    expect(records).toEqual({ estudiante: carlaCondori, registros: [] })
  })

  it('pide el detalle con su historial', async () => {
    const detail = initialDetails()[0]
    const api = mockApiWith(() => ({ body: { data: detail } }))

    expect(await getRectifiableRecord(9961)).toEqual(detail)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/registros\/9961$/)
  })

  it('pide el catálogo propio de tipos de falta', async () => {
    const api = mockApiWith(() => ({ body: { data: rectificationTypes } }))

    expect(await getRectificationFaultTypes()).toEqual(rectificationTypes)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/tipos-falta$/)
  })

  it('rectifica con POST y manda solo tipo, descripción, motivo y versión base', async () => {
    const detail = initialDetails()[1]
    const api = mockApiWith(() => ({ status: 201, body: { data: detail } }))

    const result = await rectifyRecord(9962, {
      id_tipo_falta: 2,
      descripcion: null,
      motivo: 'Se confirmó otra falta.',
      version_base: 1,
    })

    expect(api.calls[0].method).toBe('POST')
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/registros\/9962\/rectificar$/)
    expect(api.calls[0].body).toEqual({
      id_tipo_falta: 2,
      descripcion: null,
      motivo: 'Se confirmó otra falta.',
      version_base: 1,
    })
    expect(result).toEqual(detail)
  })

  it('un 409 llega como error con el mensaje del servidor', async () => {
    mockApiWith(() => ({ status: 409, body: { message: 'El registro fue rectificado.' } }))

    await expect(
      rectifyRecord(9962, { id_tipo_falta: 2, descripcion: null, motivo: 'Motivo suficiente.', version_base: 1 })
    ).rejects.toMatchObject({ status: 409, message: 'El registro fue rectificado.' })
  })
})
