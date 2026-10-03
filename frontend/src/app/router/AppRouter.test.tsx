import { render, screen, waitForElementToBeRemoved } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import { CurrentUserProvider, type Area } from '@/features/auth'
import { mockApiOnce } from '@/test/http'
import { AppRouter } from './AppRouter'

/**
 * Misma clave de `localStorage` que usa `CurrentUserProvider` para recordar el
 * área elegida en el selector de desarrollo (HU-37 la reemplaza por la sesión
 * real). No hay otra forma de fijar el área antes de montar sin exportar algo
 * nuevo del feature `auth` solo para pruebas.
 */
const AREA_STORAGE_KEY = 'sciem.dev.area'

function LocationSpy({ onChange }: { onChange: (pathname: string) => void }) {
  onChange(useLocation().pathname)
  return null
}

function renderAppRouterAt(area: Area, route: string) {
  window.localStorage.setItem(AREA_STORAGE_KEY, area)

  let pathname = ''

  render(
    <MemoryRouter initialEntries={[route]}>
      <CurrentUserProvider>
        <AppRouter />
        <LocationSpy onChange={(value) => (pathname = value)} />
      </CurrentUserProvider>
    </MemoryRouter>
  )

  return { get pathname() { return pathname } }
}

describe('AppRouter — acceso a /ambientes por área', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('un Administrador entra a Ambientes', async () => {
    mockApiOnce({ body: { data: [] } })

    const location = renderAppRouterAt('administrador', '/ambientes')

    await waitForElementToBeRemoved(() => screen.queryByRole('status'))

    expect(screen.getByRole('heading', { name: 'Registrar ambiente' })).toBeInTheDocument()
    expect(location.pathname).toBe('/ambientes')
  })

  it('un Docente que navega a /ambientes es redirigido a la home de su área', async () => {
    mockApiOnce({ body: { data: [] } })

    const location = renderAppRouterAt('docente', '/ambientes')

    expect(
      screen.queryByRole('heading', { name: 'Registrar ambiente' })
    ).not.toBeInTheDocument()
    expect(location.pathname).toBe('/materias')

    await waitForElementToBeRemoved(() => screen.queryByRole('status'))
  })

  it('un Auxiliar que navega a /ambientes es redirigido a Mis exámenes', async () => {
    mockApiOnce({ body: { data: [] } })

    const location = renderAppRouterAt('auxiliar', '/ambientes')

    expect(location.pathname).toBe('/mis-examenes')
    expect(await screen.findByText('No tiene exámenes por controlar')).toBeVisible()
  })
})
