import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { carlaCondori, initialRecords } from '@/test/riskRectificationFixtures'
import {
  getRectifiableRecord,
  getStudentRecords,
  rectifyWithoutConfirmation,
  requestRectification,
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

  it('pide los registros activos y los rectificados de un estudiante a su propia ruta', async () => {
    const api = mockApiWith(() => ({
      body: { data: { estudiante: carlaCondori, registros: [], rectificados: [] } },
    }))

    const records = await getStudentRecords(9702)

    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/estudiantes\/9702\/registros$/)
    expect(api.calls[0].url).not.toContain('/central-riesgo')
    expect(records).toEqual({ estudiante: carlaCondori, registros: [], rectificados: [] })
  })

  it('pide la ficha de un registro', async () => {
    const detail = initialRecords()[1].detail
    const api = mockApiWith(() => ({ body: { data: detail } }))

    expect(await getRectifiableRecord(9962)).toEqual(detail)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/registros\/9962$/)
  })

  it('un registro ya rectificado llega como error 404', async () => {
    mockApiWith(() => ({ status: 404, body: { message: 'No existe el registro de la Central de Riesgo indicado.' } }))

    await expect(getRectifiableRecord(9961)).rejects.toMatchObject({ status: 404 })
  })

  it('no hay catálogo de tipos de falta: la rectificación no edita el tipo', async () => {
    const api = mockApiWith(() => ({ body: { data: [] } }))

    await getStudentRecords(9702)

    expect(api.calls.map((call) => call.url).join()).not.toContain('tipos-falta')
  })

  it('envía la solicitud con POST a /solicitudes y manda solo el motivo', async () => {
    const sent = {
      id_solicitud_rect: 9980,
      id_cr_estudiante: 9962,
      estado: 'PENDIENTE',
      motivo: 'Se retira: el reporte no tenía sustento.',
      docente: { nombre_completo: 'Marcelo Quiroga Andrade' },
      fecha_solicitud: '2026-11-20T15:00:00+00:00',
      vence_en: '2026-11-27T15:00:00+00:00',
    }
    const api = mockApiWith(() => ({ status: 201, body: { data: sent } }))

    const result = await requestRectification(9962, { motivo: 'Se retira: el reporte no tenía sustento.' })

    expect(api.calls).toHaveLength(1)
    expect(api.calls[0].method).toBe('POST')
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/rectificacion-riesgo\/registros\/9962\/solicitudes$/)
    expect(api.calls[0].body).toEqual({ motivo: 'Se retira: el reporte no tenía sustento.' })
    expect(result).toEqual(sent)
  })

  it('anula sin confirmación con POST a /rectificar-sin-confirmacion', async () => {
    const record = initialRecords()[1]
    const rectified = {
      ...record.detail,
      rectificacion: { fecha: '2026-11-14T15:30:00+00:00', administrador: { nombre_completo: 'Admin' }, motivo: 'x' },
    }
    const api = mockApiWith(() => ({ status: 200, body: { data: rectified } }))

    const result = await rectifyWithoutConfirmation(9962, { motivo: 'Se retira: el reporte no tenía sustento.' })

    expect(api.calls[0].method).toBe('POST')
    expect(new URL(api.calls[0].url).pathname).toMatch(
      /\/rectificacion-riesgo\/registros\/9962\/rectificar-sin-confirmacion$/
    )
    expect(api.calls[0].body).toEqual({ motivo: 'Se retira: el reporte no tenía sustento.' })
    expect(result).toEqual(rectified)
  })

  it('un 409 llega como error con el mensaje del servidor', async () => {
    mockApiWith(() => ({ status: 409, body: { message: 'Este registro ya tiene una solicitud pendiente.' } }))

    await expect(requestRectification(9962, { motivo: 'Motivo suficiente.' })).rejects.toMatchObject({
      status: 409,
      message: 'Este registro ya tiene una solicitud pendiente.',
    })
  })
})
