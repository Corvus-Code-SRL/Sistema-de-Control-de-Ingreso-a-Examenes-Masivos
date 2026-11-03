import { afterEach, describe, expect, it, vi } from 'vitest'
import { entries, stubAuditLogApi } from '@/test/auditLogFixtures'
import { mockApiWith } from '@/test/http'
import { NO_FILTERS } from '../utils/filters'
import { getAuditLog, getAuditLogEntry, getAuditLogOptions } from './auditLogService'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('getAuditLog', () => {
  it('no manda los filtros vacíos y trae la página con su meta', async () => {
    const api = stubAuditLogApi()

    const page = await getAuditLog(NO_FILTERS)

    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/bitacora$/)
    expect([...url.searchParams.keys()]).toEqual(['page'])
    expect(page).toEqual({ items: entries, page: 1, perPage: 50, total: 5, totalPages: 1 })
  })

  it('manda cada filtro tal cual, sin convertir las fechas', async () => {
    const api = stubAuditLogApi()

    await getAuditLog({
      q: 'marcelo',
      accion: 'MODIFICAR',
      modulo: 'grupos',
      desde: '2026-10-19',
      hasta: '2026-10-20',
      page: 2,
    })

    const url = new URL(api.calls[0].url)

    expect(Object.fromEntries(url.searchParams)).toEqual({
      q: 'marcelo',
      accion: 'MODIFICAR',
      modulo: 'grupos',
      desde: '2026-10-19',
      hasta: '2026-10-20',
      page: '2',
    })
  })

  it('una respuesta sin páginas se normaliza a una', async () => {
    stubAuditLogApi({ entries: [] })

    expect((await getAuditLog(NO_FILTERS)).totalPages).toBe(1)
  })
})

describe('getAuditLogEntry', () => {
  it('pide el detalle por id', async () => {
    const api = stubAuditLogApi()

    const detail = await getAuditLogEntry(130)

    expect(api.calls[0].url).toMatch(/\/bitacora\/130$/)
    expect(detail.id_log).toBe(130)
  })

  it('un id inexistente falla como «no encontrado»', async () => {
    stubAuditLogApi()

    await expect(getAuditLogEntry(99999)).rejects.toMatchObject({ isNotFound: true })
  })
})

describe('getAuditLogOptions', () => {
  it('trae acciones y módulos', async () => {
    stubAuditLogApi()

    const options = await getAuditLogOptions()

    expect(options.acciones.map((action) => action.codigo)).toContain('MODIFICAR')
    expect(options.modulos.map((module) => module.clave)).toContain('examenes')
  })

  it('una respuesta incompleta deja los selectores vacíos en vez de fallar', async () => {
    mockApiWith(() => ({ body: { data: [] } }))

    expect(await getAuditLogOptions()).toEqual({ acciones: [], modulos: [] })
  })
})
