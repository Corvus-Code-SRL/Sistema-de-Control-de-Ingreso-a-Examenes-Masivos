import { screen, waitFor } from '@testing-library/react'

import userEvent from '@testing-library/user-event'

import { describe, expect, it, vi } from 'vitest'

import { manyEntries } from '@/test/auditLogFixtures'

import {
  setupReportTests,
  REPORT_PATH,
  DEFAULT_ROUTE,
  stubApi,
  renderPage,
  listRequests,
  loadedTable,
  generateButton,
  pdfButton,
  excelButton,
  dateInput,
  setDateInput
} from './auditLogReportTestHelpers'

setupReportTests()

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
