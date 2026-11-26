import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { entries } from '@/test/auditLogFixtures'

import {
  getAuditLogReport,
  getAuditLogReportPdf,
  getAuditLogReportExcel
} from './auditLogReportService'

import type { AuditLogReportFilters, AuditLogReportResponse } from '../types/auditLogReport.types'

const FILTERS: AuditLogReportFilters = {
  desde: '2026-11-01',
  hasta: '2026-11-30',
  q: '',
  accion: null,
  modulo: null,
  page: 1
}

const RESPONSE: AuditLogReportResponse = {
  data: entries,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 50,
    total: 120
  },
  totales: {
    total_operaciones: 120,
    total_usuarios: 2,
    por_accion: [
      {
        codigo: 'CREAR',
        etiqueta: 'Crear',
        total: 80
      },
      {
        codigo: 'MODIFICAR',
        etiqueta: 'Modificar',
        total: 40
      }
    ]
  },
  filtros: {
    desde: '2026-11-01',
    hasta: '2026-11-30',
    q: null,
    accion: null,
    modulo: null
  },
  puede_exportar: true
}

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('getAuditLogReport', () => {
  it('consulta el endpoint correcto del reporte', async () => {
    const api = mockApiWith(() => ({ body: RESPONSE }))

    await getAuditLogReport(FILTERS)

    expect(new URL(api.calls[0].url).pathname).toMatch(/\/reportes\/bitacora$/)
  })

  it('envia las fechas obligatorias sin modificarlas', async () => {
    const api = mockApiWith(() => ({ body: RESPONSE }))

    await getAuditLogReport(FILTERS)

    const url = new URL(api.calls[0].url)

    expect(url.searchParams.get('desde')).toBe('2026-11-01')
    expect(url.searchParams.get('hasta')).toBe('2026-11-30')
  })

  it('envia los filtros por usuario, accion y modulo', async () => {
    const api = mockApiWith(() => ({ body: RESPONSE }))

    await getAuditLogReport({
      ...FILTERS,
      q: 'jose perez',
      accion: 'CREAR',
      modulo: 'examenes',
      page: 2
    })

    const url = new URL(api.calls[0].url)

    expect(Object.fromEntries(url.searchParams)).toEqual({
      desde: '2026-11-01',
      hasta: '2026-11-30',
      q: 'jose perez',
      accion: 'CREAR',
      modulo: 'examenes',
      page: '2'
    })
  })

  it('omite los filtros opcionales vacios', async () => {
    const api = mockApiWith(() => ({ body: RESPONSE }))

    await getAuditLogReport(FILTERS)

    const url = new URL(api.calls[0].url)

    expect(url.searchParams.has('q')).toBe(false)
    expect(url.searchParams.has('accion')).toBe(false)
    expect(url.searchParams.has('modulo')).toBe(false)
  })

  it('elimina espacios innecesarios de la busqueda', async () => {
    const api = mockApiWith(() => ({ body: RESPONSE }))

    await getAuditLogReport({
      ...FILTERS,
      q: '  Marcelo Quiroga  '
    })

    const url = new URL(api.calls[0].url)

    expect(url.searchParams.get('q')).toBe('Marcelo Quiroga')
  })

  it('interpreta los registros, filtros y totales globales', async () => {
    mockApiWith(() => ({ body: RESPONSE }))

    const report = await getAuditLogReport(FILTERS)

    expect(report.items).toEqual(entries)
    expect(report.totals).toEqual(RESPONSE.totales)
    expect(report.filters).toEqual(RESPONSE.filtros)
    expect(report.canExport).toBe(true)
  })

  it('interpreta los metadatos de paginacion', async () => {
    mockApiWith(() => ({
      body: {
        ...RESPONSE,
        meta: {
          ...RESPONSE.meta,
          current_page: 2
        }
      }
    }))

    const report = await getAuditLogReport({
      ...FILTERS,
      page: 2
    })

    expect(report.page).toBe(2)
    expect(report.perPage).toBe(50)
    expect(report.total).toBe(120)
    expect(report.totalPages).toBe(3)
  })

  it('interpreta un reporte vacio y deshabilita la exportacion', async () => {
    mockApiWith(() => ({
      body: {
        ...RESPONSE,
        data: [],
        meta: {
          current_page: 1,
          last_page: 0,
          per_page: 50,
          total: 0
        },
        totales: {
          total_operaciones: 0,
          total_usuarios: 0,
          por_accion: []
        },
        puede_exportar: false
      }
    }))

    const report = await getAuditLogReport(FILTERS)

    expect(report.items).toEqual([])
    expect(report.totals.total_operaciones).toBe(0)
    expect(report.totals.total_usuarios).toBe(0)
    expect(report.totals.por_accion).toEqual([])
    expect(report.canExport).toBe(false)
    expect(report.totalPages).toBe(1)
  })

  it('propaga los errores de la API', async () => {
    mockApiWith(() => ({
      status: 403,
      body: {
        message: 'No tiene permisos para consultar el reporte.'
      }
    }))

    await expect(getAuditLogReport(FILTERS)).rejects.toThrow()
  })

  it('permite cancelar una solicitud mediante AbortSignal', async () => {
    const controller = new AbortController()

    const fetchMock = vi.fn(
      (_input: RequestInfo | URL, init?: RequestInit) =>
        new Promise<Response>((_resolve, reject) => {
          const signal = init?.signal

          const abort = () => {
            reject(new DOMException('Aborted', 'AbortError'))
          }

          if (signal?.aborted) {
            abort()
          } else {
            signal?.addEventListener('abort', abort, { once: true })
          }
        })
    )

    vi.stubGlobal('fetch', fetchMock)

    const request = getAuditLogReport(FILTERS, controller.signal)

    const rejection = expect(request).rejects.toThrow()

    controller.abort()

    await rejection

    expect(fetchMock).toHaveBeenCalled()
  })
})

describe('getAuditLogReportPdf', () => {
  it('solicita el PDF con los filtros y sin paginacion', async () => {
    const file = new Blob(['%PDF-1.4'], { type: 'application/pdf' })

    const api = mockApiWith(() => ({ body: file }))

    const filters = {
      ...FILTERS,
      q: 'marcelo',
      accion: 'CREAR',
      modulo: 'examenes',
      page: 3
    }

    await expect(getAuditLogReportPdf(filters)).resolves.toBeInstanceOf(Blob)

    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/reportes\/bitacora\/pdf$/)

    expect(Object.fromEntries(url.searchParams)).toEqual({
      desde: '2026-11-01',
      hasta: '2026-11-30',
      q: 'marcelo',
      accion: 'CREAR',
      modulo: 'examenes'
    })

    expect(url.searchParams.has('page')).toBe(false)
  })
})

describe('getAuditLogReportExcel', () => {
  it('solicita el Excel con los filtros y sin paginacion', async () => {
    const file = new Blob(['excel-test'], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })

    const api = mockApiWith(() => ({ body: file }))

    const filters = {
      ...FILTERS,
      q: '20381',
      accion: 'MODIFICAR',
      modulo: 'grupos',
      page: 2
    }

    await expect(getAuditLogReportExcel(filters)).resolves.toBeInstanceOf(Blob)

    const url = new URL(api.calls[0].url)

    expect(url.pathname).toMatch(/\/reportes\/bitacora\/excel$/)

    expect(Object.fromEntries(url.searchParams)).toEqual({
      desde: '2026-11-01',
      hasta: '2026-11-30',
      q: '20381',
      accion: 'MODIFICAR',
      modulo: 'grupos'
    })

    expect(url.searchParams.has('page')).toBe(false)
  })
})

describe('auditLogReportService', () => {
  it('no ofrece operaciones de escritura', async () => {
    const module = await import('./auditLogReportService')

    expect(
      Object.keys(module).filter((name) =>
        /^(create|update|delete|save|edit|remove|post|put|patch)/i.test(name)
      )
    ).toEqual([])
  })
})
