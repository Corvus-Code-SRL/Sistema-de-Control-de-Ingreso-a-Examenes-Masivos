import { screen, waitFor, within } from '@testing-library/react'

import userEvent from '@testing-library/user-event'

import { describe, expect, it } from 'vitest'

import {
  setupReportTests,
  DEFAULT_ROUTE,
  stubApi,
  renderPage,
  listRequests,
  loadedTable,
  generateButton,
  pdfButton,
  dateInput,
  setDateInput
} from './auditLogReportTestHelpers'

setupReportTests()

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
