import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { faultTypes, records } from '@/test/riskCenterFixtures'
import {
  RISK_CENTER_PER_PAGE,
  getFaultTypes,
  getRiskCenterEvidence,
  getRiskCenterRecord,
  getRiskCenterRecords,
} from './riskCenterService'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('riskCenterService', () => {
  it('pide la lista con la búsqueda, el tipo, la página y el tamaño de página', async () => {
    const api = mockApiWith(() => ({
      body: { data: records, meta: { current_page: 2, last_page: 4, per_page: 10, total: 33 } },
    }))

    const page = await getRiskCenterRecords({ q: 'jose', tipo: 2, page: 2 })
    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/central-riesgo$/)
    expect(Object.fromEntries(url.searchParams)).toEqual({
      q: 'jose',
      tipo: '2',
      page: '2',
      per_page: String(RISK_CENTER_PER_PAGE),
    })
    expect(page).toEqual({ items: records, page: 2, perPage: 10, total: 33, totalPages: 4 })
  })

  it('no manda q ni tipo cuando no hay filtros', async () => {
    const api = mockApiWith(() => ({
      body: { data: [], meta: { current_page: 1, last_page: 0, per_page: 10, total: 0 } },
    }))

    const page = await getRiskCenterRecords({ q: '', tipo: null, page: 1 })
    const url = new URL(api.calls[0].url)

    expect(url.searchParams.has('q')).toBe(false)
    expect(url.searchParams.has('tipo')).toBe(false)
    expect(page.totalPages).toBe(1)
  })

  it('pide el detalle por id y devuelve el registro', async () => {
    const api = mockApiWith(() => ({ body: { data: { id_cr_estudiante: 9945 } } }))

    await expect(getRiskCenterRecord(9945)).resolves.toEqual({ id_cr_estudiante: 9945 })
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/central-riesgo\/9945$/)
  })

  it('pide los tipos de falta al catálogo compartido', async () => {
    const api = mockApiWith(() => ({ body: { data: faultTypes } }))

    await expect(getFaultTypes()).resolves.toEqual(faultTypes)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/tipos-falta$/)
  })

  it('pide la evidencia como archivo a su endpoint autenticado', async () => {
    const file = new Blob(['png'], { type: 'image/png' })
    const api = mockApiWith(() => ({ body: file }))

    await expect(getRiskCenterEvidence(9945)).resolves.toBeInstanceOf(Blob)
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/central-riesgo\/9945\/evidencia$/)
  })

  it('no ofrece ninguna operación de escritura', async () => {
    const module = await import('./riskCenterService')

    expect(Object.keys(module).filter((name) => /^(create|update|delete|save|edit|remove|post|put|patch)/i.test(name))).toEqual([])
  })
})
