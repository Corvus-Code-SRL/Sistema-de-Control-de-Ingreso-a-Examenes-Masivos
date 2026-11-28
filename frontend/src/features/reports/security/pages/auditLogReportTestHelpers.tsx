import { fireEvent, screen, within } from '@testing-library/react'

import { useLocation } from 'react-router-dom'

import { afterEach, beforeEach, vi } from 'vitest'

import { entries, options, plain, laPazDay } from '@/test/auditLogFixtures'

import { mockApiWith, type RecordedRequest } from '@/test/http'

import { renderWithRouter } from '@/test/render'
import { AuditLogReportPage } from './AuditLogReportPage'

import type { AuditLogEntry } from '@/features/audit-log'

export const REPORT_PATH = '/reportes/bitacora'

export const DEFAULT_ROUTE =
  '/reportes/bitacora?desde=2026-10-01&hasta=2026-10-31'

interface StubOptions {
  records?: AuditLogEntry[]
  listFail?: boolean
  optionsFail?: boolean
  exportFail?: boolean
}

export function stubApi(config: StubOptions = {}) {
  const records = config.records ?? entries

  return mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/bitacora/opciones') {
      return config.optionsFail
        ? {
            status: 500,
            body: {
              message: 'No se pudieron cargar las opciones.',
            },
          }
        : { body: { data: options } }
    }

    if (path === `${REPORT_PATH}/pdf`) {
      return config.exportFail
        ? {
            status: 500,
            body: { message: 'Error de descarga.' },
          }
        : {
            body: new Blob(['%PDF-1.4'], {
              type: 'application/pdf',
            }),
          }
    }

    if (path === `${REPORT_PATH}/excel`) {
      return config.exportFail
        ? {
            status: 500,
            body: { message: 'Error de descarga.' },
          }
        : {
            body: new Blob(['excel-test'], {
              type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            }),
          }
    }

    if (path !== REPORT_PATH) {
      return undefined
    }

    if (config.listFail) {
      return {
        status: 500,
        body: {
          message: 'No se pudo cargar el reporte.',
        },
      }
    }

    const desde = url.searchParams.get('desde') ?? ''
    const hasta = url.searchParams.get('hasta') ?? ''
    const q = url.searchParams.get('q')
    const accion = url.searchParams.get('accion')
    const modulo = url.searchParams.get('modulo')

    const terms = plain(q ?? '')
      .split(/\s+/)
      .filter(Boolean)

    const rows = records.filter((row) => {
      const day = laPazDay(row.fecha_hora)

      return (
        day >= desde &&
        day <= hasta &&
        (accion === null || row.accion.codigo === accion) &&
        (modulo === null || row.modulo.clave === modulo) &&
        terms.every(
          (term) =>
            plain(row.usuario.nombre_completo ?? '').includes(term) ||
            (row.usuario.cod_sis ?? '').includes(term)
        )
      )
    })

    const actionTotals = new Map<
      string | null,
      {
        codigo: string | null
        etiqueta: string
        total: number
      }
    >()

    for (const row of rows) {
      const code = row.accion.codigo
      const previous = actionTotals.get(code)

      actionTotals.set(code, {
        codigo: code,
        etiqueta: row.accion.etiqueta,
        total: (previous?.total ?? 0) + 1,
      })
    }

    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = 50
    const start = (page - 1) * perPage

    return {
      body: {
        data: rows.slice(start, start + perPage),

        meta: {
          current_page: page,
          last_page: Math.max(
            1,
            Math.ceil(rows.length / perPage)
          ),
          per_page: perPage,
          total: rows.length,
        },

        totales: {
          total_operaciones: rows.length,
          total_usuarios: new Set(
            rows.map((row) => row.usuario.id_usuario)
          ).size,
          por_accion: Array.from(actionTotals.values()),
        },

        filtros: {
          desde,
          hasta,
          q,
          accion,
          modulo,
        },

        puede_exportar: rows.length > 0,
      },
    }
  })
}

function LocationProbe() {
  const { pathname, search } = useLocation()

  return (
    <output data-testid="location">
      {`${pathname}${search}`}
    </output>
  )
}

export function renderPage(route = DEFAULT_ROUTE) {
  return renderWithRouter(
    <>
      <AuditLogReportPage />
      <LocationProbe />
    </>,
    {
      route,
      path: REPORT_PATH,
    }
  )
}

export function listRequests(calls: RecordedRequest[]) {
  return calls.filter((call) => {
    const path = new URL(call.url).pathname
      .replace(/^.*\/api/, '')

    return call.method === 'GET' && path === REPORT_PATH
  })
}

export async function loadedTable() {
  return within(await screen.findByRole('table'))
}

export function generateButton() {
  return screen.getByRole('button', {
    name: 'Generar reporte',
  })
}

export function pdfButton() {
  return screen.getByRole('button', {
    name: 'Descargar PDF',
  })
}

export function excelButton() {
  return screen.getByRole('button', {
    name: 'Descargar Excel',
  })
}

export function dateInput(name: RegExp) {
  return screen.getByLabelText(name)
}

export function setDateInput(input: HTMLElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(
    HTMLInputElement.prototype,
    'value'
  )?.set

  setter?.call(input, value)

  fireEvent.input(input)
  fireEvent.change(input)
}

export function setupReportTests() {
  beforeEach(() => {
    vi.setSystemTime(new Date('2026-11-15T12:00:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })
}
