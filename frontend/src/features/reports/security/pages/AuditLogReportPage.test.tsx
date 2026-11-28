import { screen, waitFor, within } from '@testing-library/react'

import userEvent from '@testing-library/user-event'

import { describe, expect, it } from 'vitest'

import { manyEntries } from '@/test/auditLogFixtures'

import {
  setupReportTests,
  stubApi,
  renderPage,
  listRequests,
  loadedTable,
  dateInput
} from './auditLogReportTestHelpers'

setupReportTests()

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
