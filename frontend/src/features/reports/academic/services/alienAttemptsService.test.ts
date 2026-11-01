import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { getAlienAttempts, getAlienAttemptsPdf, ALIEN_ATTEMPTS_PER_PAGE } from './alienAttemptsService'
import { getFaculties } from './facultiesService'

const SAMPLE_ROW = {
  id_estudiante: 42,
  nombre_completo: 'Sergio Vargas Ibáñez',
  cod_sis: '202178004',
  intentos: 3,
  examenes: ['1er Parcial Cálculo I', '2do Parcial Álgebra I'],
  id_cr_estudiante: 9945,
}

const SAMPLE_META = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 1,
  from: 1,
  to: 1,
  total_intentos: 3,
  total_estudiantes: 1,
  total_en_central_riesgo: 1,
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('alienAttemptsService', () => {
  it('pide el reporte con los filtros, la página y el tamaño de página', async () => {
    const api = mockApiWith(() => ({ body: { data: [SAMPLE_ROW], meta: SAMPLE_META } }))

    const page = await getAlienAttempts({ desde: '2026-10-01', hasta: '2026-10-15', facultad: 7, page: 2 })
    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/reportes\/intentos-ajenos$/)
    expect(Object.fromEntries(url.searchParams)).toEqual({
      desde: '2026-10-01',
      hasta: '2026-10-15',
      facultad: '7',
      page: '2',
      per_page: String(ALIEN_ATTEMPTS_PER_PAGE),
    })
    expect(page).toEqual({
      items: [SAMPLE_ROW],
      stats: { intentos: 3, estudiantes: 1, enCentralRiesgo: 1 },
      page: 1,
      perPage: 10,
      total: 1,
      totalPages: 1,
    })
  })

  it('no manda facultad cuando no hay filtro', async () => {
    const api = mockApiWith(() => ({ body: { data: [], meta: { ...SAMPLE_META, total: 0 } } }))

    await getAlienAttempts({ desde: '2026-10-01', hasta: '2026-10-15', facultad: null, page: 1 })
    const url = new URL(api.calls[0].url)

    expect(url.searchParams.has('facultad')).toBe(false)
  })

  it('pide el PDF como blob con los mismos filtros, sin page', async () => {
    const file = new Blob(['%PDF-1.4'], { type: 'application/pdf' })
    const api = mockApiWith(() => ({ body: file }))

    await expect(
      getAlienAttemptsPdf({ desde: '2026-10-01', hasta: '2026-10-15', facultad: 7 })
    ).resolves.toBeInstanceOf(Blob)
    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/reportes\/intentos-ajenos\/pdf$/)
    expect(Object.fromEntries(url.searchParams)).toEqual({
      desde: '2026-10-01',
      hasta: '2026-10-15',
      facultad: '7',
    })
  })

  it('pide el catálogo de facultades', async () => {
    const api = mockApiWith(() => ({
      body: { data: [{ id_facultad: 7, nombre: 'FCyT', codigo: 'FCYT' }] },
    }))

    await expect(getFaculties()).resolves.toEqual([{ id_facultad: 7, nombre: 'FCyT', codigo: 'FCYT' }])
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/facultades$/)
  })

  it('no ofrece ninguna operación de escritura', async () => {
    const module = await import('./alienAttemptsService')

    expect(
      Object.keys(module).filter((name) => /^(create|update|delete|save|edit|remove|post|put|patch)/i.test(name))
    ).toEqual([])
  })
})
