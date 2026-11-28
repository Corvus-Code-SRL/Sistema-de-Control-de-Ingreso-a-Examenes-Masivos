import {
  fireEvent,
  screen,
  waitFor,
  within,
} from '@testing-library/react'

import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'

import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'

import {
  entries,
  manyEntries,
  options,
  plain,
  laPazDay,
} from '@/test/auditLogFixtures'

import {
  mockApiWith,
  type RecordedRequest,
} from '@/test/http'

import { renderWithRouter } from '@/test/render'
import { AuditLogReportPage } from './AuditLogReportPage'

import type {
  AuditLogEntry,
} from '@/features/audit-log'

const REPORT_PATH = '/reportes/bitacora'

const DEFAULT_ROUTE =
  '/reportes/bitacora?desde=2026-10-01&hasta=2026-10-31'

interface StubOptions {
  records?: AuditLogEntry[]
  listFail?: boolean
  optionsFail?: boolean
  exportFail?: boolean
}

function stubApi(config: StubOptions = {}) {
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

function renderPage(route = DEFAULT_ROUTE) {
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

function listRequests(calls: RecordedRequest[]) {
  return calls.filter((call) => {
    const path = new URL(call.url).pathname
      .replace(/^.*\/api/, '')

    return call.method === 'GET' && path === REPORT_PATH
  })
}

async function loadedTable() {
  return within(await screen.findByRole('table'))
}

function generateButton() {
  return screen.getByRole('button', {
    name: 'Generar reporte',
  })
}

function pdfButton() {
  return screen.getByRole('button', {
    name: 'Descargar PDF',
  })
}

function excelButton() {
  return screen.getByRole('button', {
    name: 'Descargar Excel',
  })
}

function dateInput(name: RegExp) {
  return screen.getByLabelText(name)
}

function setDateInput(input: HTMLElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(
    HTMLInputElement.prototype,
    'value'
  )?.set

  setter?.call(input, value)

  fireEvent.input(input)
  fireEvent.change(input)
}

beforeEach(() => {
  vi.setSystemTime(new Date('2026-11-15T12:00:00Z'))
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('AuditLogReportPage — visualizacion', () => {
  it('muestra el titulo del reporte', async () => {
    stubApi()
    renderPage()

    expect(
      screen.getByRole('heading', {
        name: 'Generar reporte de bitácora',
      })
    ).toBeInTheDocument()

    await loadedTable()
  })

  it('muestra los cinco filtros', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    expect(dateInput(/^Desde/)).toBeInTheDocument()
    expect(dateInput(/^Hasta/)).toBeInTheDocument()

    expect(
      screen.getByRole('searchbox', { name: 'Usuario' })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('combobox', { name: 'Acción' })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('combobox', { name: 'Módulo' })
    ).toBeInTheDocument()
  })

  it('muestra las cinco columnas requeridas', async () => {
    stubApi()
    renderPage()

    const table = await loadedTable()

    expect(
      table.getAllByRole('columnheader').map(
        (header) => header.textContent
      )
    ).toEqual([
      'Fecha y hora',
      'Usuario',
      'Acción',
      'Módulo',
      'Registro afectado',
    ])
  })

  it('muestra nombre, SIS y registro afectado', async () => {
    stubApi()
    renderPage()

    const table = await loadedTable()
    const row = within(table.getAllByRole('row')[1])

    expect(
      row.getByText('Marcelo Quiroga Andrade')
    ).toBeInTheDocument()

    expect(row.getByText('10452')).toBeInTheDocument()
    expect(row.getByText('Grupo #7')).toBeInTheDocument()
  })

  it('muestra los registros del mas reciente al mas antiguo', async () => {
    stubApi()
    renderPage()

    const table = await loadedTable()
    const rows = table.getAllByRole('row').slice(1)

    expect(rows).toHaveLength(5)

    expect(rows[0]).toHaveTextContent(
      '20/10/2026 10:21'
    )

    expect(rows[1]).toHaveTextContent(
      '19/10/2026 23:30'
    )
  })

  it('muestra el total de operaciones', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    expect(
      within(screen.getByTestId('stat-operaciones'))
        .getByText('5')
    ).toBeInTheDocument()
  })

  it('muestra el total de usuarios distintos', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    expect(
      within(screen.getByTestId('stat-usuarios'))
        .getByText('5')
    ).toBeInTheDocument()
  })

  it('muestra los totales por accion', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    const stats = within(
      screen.getByTestId('stat-acciones')
    )

    expect(stats.getByText('Crear')).toBeInTheDocument()
    expect(stats.getByText('Modificar')).toBeInTheDocument()
    expect(stats.getByText('2')).toBeInTheDocument()
  })
})

describe('AuditLogReportPage — fechas', () => {
  it('exige la fecha de inicio', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    setDateInput(dateInput(/^Desde/), '')

    expect(screen.getByRole('alert')).toHaveTextContent(
      'La fecha de inicio es obligatoria.'
    )

    expect(generateButton()).toBeDisabled()
  })

  it('exige la fecha de fin', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    setDateInput(dateInput(/^Hasta/), '')

    expect(screen.getByRole('alert')).toHaveTextContent(
      'La fecha de fin es obligatoria.'
    )

    expect(generateButton()).toBeDisabled()
  })

  it('rechaza fechas invertidas sin consultar nuevamente', async () => {
    const api = stubApi()

    renderPage()
    await loadedTable()

    const previous = listRequests(api.calls).length

    setDateInput(dateInput(/^Desde/), '2026-11-01')

    expect(screen.getByRole('alert')).toHaveTextContent(
      'La fecha de inicio debe ser anterior a la fecha de fin.'
    )

    expect(generateButton()).toBeDisabled()
    expect(listRequests(api.calls)).toHaveLength(previous)
  })

  it('permite consultar un mismo dia', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    setDateInput(dateInput(/^Desde/), '2026-10-20')
    setDateInput(dateInput(/^Hasta/), '2026-10-20')

    expect(generateButton()).toBeEnabled()

    await user.click(generateButton())

    await waitFor(() => {
      const requests = listRequests(api.calls)
      const last = new URL(requests[requests.length - 1].url)

      expect(last.searchParams.get('desde')).toBe('2026-10-20')
      expect(last.searchParams.get('hasta')).toBe('2026-10-20')
    })
  })
})

describe('AuditLogReportPage — filtros', () => {
  it.each([
    ['nombre', 'marcelo', 'Marcelo Quiroga Andrade'],
    ['codigo SIS', '10452', 'Marcelo Quiroga Andrade'],
    ['nombre sin tildes', 'jose perez', 'José Luis Pérez Núñez'],
  ])('busca por %s', async (_kind, query, expected) => {
    const user = userEvent.setup()

    stubApi()
    renderPage()
    await loadedTable()

    await user.type(
      screen.getByRole('searchbox', { name: 'Usuario' }),
      query
    )

    await user.click(generateButton())

    await waitFor(() => {
      const table = within(screen.getByRole('table'))

      expect(table.getAllByRole('row')).toHaveLength(2)
      expect(table.getByText(expected)).toBeInTheDocument()
    })
  })

  it('no consulta mientras se escriben los filtros', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    const previous = listRequests(api.calls).length

    await user.type(
      screen.getByRole('searchbox', { name: 'Usuario' }),
      'marcelo'
    )

    expect(listRequests(api.calls)).toHaveLength(previous)

    await user.click(generateButton())

    await waitFor(() => {
      expect(listRequests(api.calls).length)
        .toBeGreaterThan(previous)
    })
  })

  it('recupera los filtros combinados desde la URL', async () => {
    stubApi()

    renderPage(
      `${DEFAULT_ROUTE}&q=marcelo&accion=MODIFICAR&modulo=grupos`
    )

    const table = await loadedTable()

    expect(table.getAllByRole('row')).toHaveLength(2)

    expect(
      screen.getByRole('searchbox', { name: 'Usuario' })
    ).toHaveValue('marcelo')

    expect(
      screen.getByRole('combobox', { name: 'Acción' })
    ).toHaveTextContent('Modificar')

    expect(
      screen.getByRole('combobox', { name: 'Módulo' })
    ).toHaveTextContent('Grupos')
  })

  it('limpia los filtros aplicados', async () => {
    const user = userEvent.setup()

    stubApi()
    renderPage(`${DEFAULT_ROUTE}&q=marcelo`)

    await loadedTable()

    await user.click(
      screen.getByRole('button', {
        name: 'Limpiar filtros',
      })
    )

    await waitFor(() => {
      expect(
        screen.getByRole('searchbox', { name: 'Usuario' })
      ).toHaveValue('')

      expect(
        screen.getByTestId('location')
      ).not.toHaveTextContent('q=marcelo')
    })
  })
})

describe('AuditLogReportPage — paginacion', () => {
  it('muestra 50 registros y permite avanzar de pagina', async () => {
    const user = userEvent.setup()
    const api = stubApi({
      records: manyEntries(120),
    })

    renderPage()

    const table = await loadedTable()

    expect(table.getAllByRole('row')).toHaveLength(51)

    await user.click(
      screen.getByRole('button', { name: 'Siguiente' })
    )

    await waitFor(() => {
      expect(screen.getByTestId('location'))
        .toHaveTextContent('page=2')

      expect(
        listRequests(api.calls).some(
          (call) => call.url.includes('page=2')
        )
      ).toBe(true)
    })

    await loadedTable()
  })

  it('conserva los totales globales al cambiar de pagina', async () => {
    const user = userEvent.setup()

    stubApi({ records: manyEntries(120) })
    renderPage()
    await loadedTable()

    await user.click(
      screen.getByRole('button', { name: 'Siguiente' })
    )

    await loadedTable()

    expect(
      within(screen.getByTestId('stat-operaciones'))
        .getByText('120')
    ).toBeInTheDocument()

    expect(
      within(screen.getByTestId('stat-usuarios'))
        .getByText('1')
    ).toBeInTheDocument()
  })
})

describe('AuditLogReportPage — estados y descargas', () => {
  it('muestra el estado sin resultados', async () => {
    stubApi({ records: [] })
    renderPage()

    expect(
      await screen.findByText('Sin operaciones en este rango')
    ).toBeInTheDocument()

    expect(screen.queryByRole('table'))
      .not.toBeInTheDocument()
  })

  it('deshabilita ambas descargas cuando no hay resultados', async () => {
    stubApi({ records: [] })
    renderPage()

    await screen.findByText('Sin operaciones en este rango')

    expect(pdfButton()).toBeDisabled()
    expect(excelButton()).toBeDisabled()
  })

  it('habilita las descargas cuando existen resultados', async () => {
    stubApi()
    renderPage()
    await loadedTable()

    expect(pdfButton()).toBeEnabled()
    expect(excelButton()).toBeEnabled()
  })

  it('deshabilita las descargas durante la carga inicial', async () => {
    stubApi()
    renderPage()

    expect(pdfButton()).toBeDisabled()
    expect(excelButton()).toBeDisabled()

    await loadedTable()
  })

  it('deshabilita las descargas con filtros sin aplicar', async () => {
    const user = userEvent.setup()

    stubApi()
    renderPage()
    await loadedTable()

    await user.type(
      screen.getByRole('searchbox', { name: 'Usuario' }),
      'marcelo'
    )

    expect(pdfButton()).toBeDisabled()
    expect(excelButton()).toBeDisabled()
  })

  it('permite consultar cuando falla el catalogo de opciones', async () => {
    stubApi({ optionsFail: true })
    renderPage()
    await loadedTable()

    expect(
      await screen.findByText(
        /No se pudieron cargar las acciones y los módulos/
      )
    ).toBeInTheDocument()

    expect(generateButton()).toBeEnabled()
  })

  it('no realiza operaciones de escritura', async () => {
    const api = stubApi()

    renderPage()
    await loadedTable()

    expect(
      api.calls.every((call) => call.method === 'GET')
    ).toBe(true)
  })
})

describe('AuditLogReportPage — pruebas complementarias', () => {
  function mockDownload() {
    const create = vi.fn(() => 'blob:hu068-test')
    const revoke = vi.fn()

    const previousCreate =
      Object.getOwnPropertyDescriptor(URL, 'createObjectURL')

    const previousRevoke =
      Object.getOwnPropertyDescriptor(URL, 'revokeObjectURL')

    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: create,
    })

    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: revoke,
    })

    const filenames: string[] = []

    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        filenames.push(this.download)
      })

    return {
      create,
      revoke,
      filenames,
      restore() {
        click.mockRestore()

        if (previousCreate) {
          Object.defineProperty(
            URL,
            'createObjectURL',
            previousCreate
          )
        } else {
          Reflect.deleteProperty(URL, 'createObjectURL')
        }

        if (previousRevoke) {
          Object.defineProperty(
            URL,
            'revokeObjectURL',
            previousRevoke
          )
        } else {
          Reflect.deleteProperty(URL, 'revokeObjectURL')
        }
      },
    }
  }

  it.each([
    ['pdf', 'pdf', 'Descargar PDF'],
    ['excel', 'xlsx', 'Descargar Excel'],
  ])(
    'descarga %s con todos los filtros aplicados',
    async (format, extension, buttonName) => {
      const download = mockDownload()

      try {
        const user = userEvent.setup()
        const api = stubApi()

        renderPage(
          `${DEFAULT_ROUTE}&q=marcelo&accion=MODIFICAR&modulo=grupos`
        )

        await loadedTable()

        await user.click(
          screen.getByRole('button', {
            name: buttonName,
          })
        )

        await waitFor(() => {
          expect(download.filenames).toContain(
            `reporte-bitacora-20261001-20261031.${extension}`
          )
        })

        const request = api.calls.find((call) =>
          new URL(call.url).pathname.endsWith(
            `${REPORT_PATH}/${format}`
          )
        )

        expect(request).toBeDefined()

        const url = new URL(request!.url)

        expect(request!.method).toBe('GET')
        expect(url.searchParams.get('desde')).toBe('2026-10-01')
        expect(url.searchParams.get('hasta')).toBe('2026-10-31')
        expect(url.searchParams.get('q')).toBe('marcelo')
        expect(url.searchParams.get('accion')).toBe('MODIFICAR')
        expect(url.searchParams.get('modulo')).toBe('grupos')
        expect(url.searchParams.has('page')).toBe(false)

        expect(download.create).toHaveBeenCalledTimes(1)

        expect(download.revoke).toHaveBeenCalledWith(
          'blob:hu068-test'
        )
      } finally {
        download.restore()
      }
    }
  )

  it.each([
    ['pdf', 'Descargar PDF'],
    ['excel', 'Descargar Excel'],
  ])(
    'exporta %s sin limitar los registros a la pagina actual',
    async (format, buttonName) => {
      const download = mockDownload()

      try {
        const user = userEvent.setup()

        const api = stubApi({
          records: manyEntries(120),
        })

        renderPage(`${DEFAULT_ROUTE}&page=2`)

        await loadedTable()

        expect(screen.getByTestId('location'))
          .toHaveTextContent('page=2')

        await user.click(
          screen.getByRole('button', {
            name: buttonName,
          })
        )

        await waitFor(() => {
          expect(download.create).toHaveBeenCalledTimes(1)
        })

        const request = api.calls.find((call) =>
          new URL(call.url).pathname.endsWith(
            `${REPORT_PATH}/${format}`
          )
        )

        expect(request).toBeDefined()

        expect(
          new URL(request!.url).searchParams.has('page')
        ).toBe(false)
      } finally {
        download.restore()
      }
    }
  )

  it.each([
    ['PDF', 'Descargar PDF'],
    ['Excel', 'Descargar Excel'],
  ])(
    'muestra un mensaje cuando falla la descarga de %s',
    async (format, buttonName) => {
      const user = userEvent.setup()

      stubApi({ exportFail: true })

      renderPage()
      await loadedTable()

      await user.click(
        screen.getByRole('button', {
          name: buttonName,
        })
      )

      expect(
        await screen.findByText(
          `No se pudo descargar el ${format}. Intente nuevamente.`
        )
      ).toBeInTheDocument()

      await waitFor(() => {
        expect(pdfButton()).toBeEnabled()
        expect(excelButton()).toBeEnabled()
      })
    }
  )

  it('deshabilita las descargas cuando falla la consulta', async () => {
    stubApi({ listFail: true })

    renderPage()

    await waitFor(() => {
      expect(generateButton()).toBeEnabled()
    })

    expect(screen.queryByRole('table'))
      .not.toBeInTheDocument()

    expect(pdfButton()).toBeDisabled()
    expect(excelButton()).toBeDisabled()
  })

  it('permite volver a generar el mismo reporte', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    const previous = listRequests(api.calls).length

    await user.click(generateButton())

    await waitFor(() => {
      expect(listRequests(api.calls).length)
        .toBeGreaterThan(previous)
    })

    await loadedTable()
  })

  it('reinicia la paginacion al aplicar nuevos filtros', async () => {
    const user = userEvent.setup()

    const api = stubApi({
      records: manyEntries(120),
    })

    renderPage(`${DEFAULT_ROUTE}&page=2`)
    await loadedTable()

    await user.type(
      screen.getByRole('searchbox', {
        name: 'Usuario',
      }),
      '10452'
    )

    await user.click(generateButton())

    await waitFor(() => {
      const requests = listRequests(api.calls)
      const last = new URL(
        requests[requests.length - 1].url
      )

      expect(last.searchParams.get('q')).toBe('10452')
      expect(last.searchParams.get('page')).toBe('1')

      expect(screen.getByTestId('location'))
        .not.toHaveTextContent('page=2')
    })
  })

  it('impide descargar cuando el rango es invalido', async () => {
    stubApi()

    renderPage()
    await loadedTable()

    setDateInput(
      dateInput(/^Desde/),
      '2026-11-01'
    )

    expect(generateButton()).toBeDisabled()
    expect(pdfButton()).toBeDisabled()
    expect(excelButton()).toBeDisabled()
  })
})


describe('HU-068 — filtros interactivos', () => {
  it('selecciona una accion y la aplica al generar', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    const previous = listRequests(api.calls).length

    await user.click(
      screen.getByRole('combobox', {
        name: 'Acción',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Modificar',
      })
    )

    expect(
      listRequests(api.calls)
    ).toHaveLength(previous)

    expect(pdfButton()).toBeDisabled()

    await user.click(generateButton())

    await waitFor(() => {
      expect(
        screen.getByTestId('location')
      ).toHaveTextContent('accion=MODIFICAR')

      expect(
        within(screen.getByRole('table'))
          .getAllByRole('row')
      ).toHaveLength(2)
    })
  })

  it('selecciona un modulo y lo aplica al generar', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    const previous = listRequests(api.calls).length

    await user.click(
      screen.getByRole('combobox', {
        name: 'Módulo',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Exámenes',
      })
    )

    expect(
      listRequests(api.calls)
    ).toHaveLength(previous)

    await user.click(generateButton())

    await waitFor(() => {
      expect(
        screen.getByTestId('location')
      ).toHaveTextContent('modulo=examenes')

      expect(
        within(screen.getByRole('table'))
          .getAllByRole('row')
      ).toHaveLength(3)
    })
  })

  it('combina los cinco filtros desde la interfaz', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage()
    await loadedTable()

    setDateInput(dateInput(/^Desde/), '2026-10-19')
    setDateInput(dateInput(/^Hasta/), '2026-10-20')

    await user.type(
      screen.getByRole('searchbox', {
        name: 'Usuario',
      }),
      '10452'
    )

    await user.click(
      screen.getByRole('combobox', {
        name: 'Acción',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Modificar',
      })
    )

    await user.click(
      screen.getByRole('combobox', {
        name: 'Módulo',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Grupos',
      })
    )

    await user.click(generateButton())

    await waitFor(() => {
      const requests = listRequests(api.calls)

      const last = new URL(
        requests[requests.length - 1].url
      )

      expect(last.searchParams.get('desde'))
        .toBe('2026-10-19')

      expect(last.searchParams.get('hasta'))
        .toBe('2026-10-20')

      expect(last.searchParams.get('q'))
        .toBe('10452')

      expect(last.searchParams.get('accion'))
        .toBe('MODIFICAR')

      expect(last.searchParams.get('modulo'))
        .toBe('grupos')

      const table = within(
        screen.getByRole('table')
      )

      expect(table.getAllByRole('row'))
        .toHaveLength(2)

      expect(
        table.getByText('Marcelo Quiroga Andrade')
      ).toBeInTheDocument()
    })
  })

  it('elimina los filtros de accion y modulo', async () => {
    const user = userEvent.setup()
    const api = stubApi()

    renderPage(
      `${DEFAULT_ROUTE}&accion=MODIFICAR&modulo=grupos`
    )

    await loadedTable()

    await user.click(
      screen.getByRole('combobox', {
        name: 'Acción',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Todas las acciones',
      })
    )

    await user.click(
      screen.getByRole('combobox', {
        name: 'Módulo',
      })
    )

    await user.click(
      await screen.findByRole('option', {
        name: 'Todos los módulos',
      })
    )

    await user.click(generateButton())

    await waitFor(() => {
      const requests = listRequests(api.calls)

      const last = new URL(
        requests[requests.length - 1].url
      )

      expect(last.searchParams.has('accion'))
        .toBe(false)

      expect(last.searchParams.has('modulo'))
        .toBe(false)

      expect(
        within(screen.getByRole('table'))
          .getAllByRole('row')
      ).toHaveLength(6)
    })
  })
})
