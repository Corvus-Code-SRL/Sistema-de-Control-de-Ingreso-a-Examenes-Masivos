import { act, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '@/test/renderApp'
import { distributionLoaded, mockDistributionBackend } from '@/test/roomDistributionBackend'
import { distributionExamFixture } from '@/test/roomDistributionFixtures'

afterEach(() => { window.localStorage.clear(); window.sessionStorage.clear(); vi.unstubAllGlobals() })

describe('salida de la distribución con ajustes locales', () => {
  it('confirma un cambio de examen y no consulta el otro hasta aceptar', async () => {
    const api = mockDistributionBackend({ exams: [distributionExamFixture(),
      distributionExamFixture({ id_examen: 13, nombre: 'Segundo parcial' })] })
    renderApp('/examenes/distribucion')
    await distributionLoaded()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    await userEvent.click(screen.getByRole('combobox', { name: 'Examen' }))
    await userEvent.click(screen.getByRole('option', { name: /Segundo parcial/ }))
    const dialog = await screen.findByRole('dialog', { name: '¿Salir sin guardar?' })
    expect(api.calls.some((call) => /\/13\/distribucion$/.test(call.url))).toBe(false)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Salir sin guardar' }))
    await waitFor(() => expect(api.calls.some((call) => /\/13\/distribucion$/.test(call.url))).toBe(true))
    await waitFor(() => expect(screen.queryByText(/4 cambios sin guardar/)).not.toBeInTheDocument())
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(0)
  })
  it('un enlace interno del sidebar pide confirmación y permite seguir editando', async () => {
    mockDistributionBackend()
    const app = renderApp('/examenes/distribucion')
    await distributionLoaded()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    await userEvent.click(screen.getByRole('button', { name: 'Grupos académicos y nóminas' }))
    await userEvent.click(screen.getByRole('link', { name: 'Materias' }))
    const dialog = await screen.findByRole('dialog', { name: '¿Salir sin guardar?' })
    expect(app.pathname).toBe('/examenes/distribucion')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Seguir editando' }))
    expect(screen.getByText(/4 cambios sin guardar/)).toBeInTheDocument()
    await userEvent.click(screen.getByRole('link', { name: 'Materias' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Salir sin guardar' }))
    await waitFor(() => expect(app.pathname).toBe('/materias'))
  })
  it('el botón atrás pide confirmación y permite seguir editando', async () => {
    mockDistributionBackend()
    renderApp('/examenes/distribucion')
    await distributionLoaded()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    act(() => { window.dispatchEvent(new PopStateEvent('popstate')) })
    const dialog = await screen.findByRole('dialog', { name: '¿Salir sin guardar?' })
    await userEvent.click(within(dialog).getByRole('button', { name: 'Seguir editando' }))
    expect(screen.getByText(/4 cambios sin guardar/)).toBeInTheDocument()
  })
})
