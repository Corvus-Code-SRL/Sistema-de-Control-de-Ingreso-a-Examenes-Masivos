import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { TOKEN_STORAGE_KEY, meBody, type AccountKey } from '@/test/authFixtures'
import { renderApp } from '@/test/renderApp'

/**
 * Las áreas salen del rol de la sesión. Sin sesión, toda ruta protegida redirige al login.
 */
function stubBackend(session: AccountKey | null) {
  if (session) {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
  }

  return mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      return session ? { body: meBody(session) } : { status: 401, body: { message: 'Unauthenticated.' } }
    }

    if (url.endsWith('/materias')) {
      return { body: { data: [], meta: { total: 0, total_mias: 0, id_periodo_activo: 1 } } }
    }

    return { body: { data: [] } }
  })
}

describe('AppRouter — acceso a /ambientes por área', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('un Administrador entra a Ambientes', async () => {
    stubBackend('administrador')

    const app = renderApp('/ambientes')

    await waitFor(() =>
      expect(screen.getByRole('heading', { name: 'Registrar ambiente' })).toBeInTheDocument()
    )
    expect(app.pathname).toBe('/ambientes')
  })

  it('un Docente que navega a /ambientes es redirigido a la home de su área', async () => {
    stubBackend('docente')

    const app = renderApp('/ambientes')

    await waitFor(() => expect(app.pathname).toBe('/materias'))
    expect(
      screen.queryByRole('heading', { name: 'Registrar ambiente' })
    ).not.toBeInTheDocument()

  })

  it('sin sesión cualquier ruta protegida lleva al login', async () => {
    stubBackend(null)

    const app = renderApp('/ambientes')

    await waitFor(() => expect(app.pathname).toBe('/login'))
    expect(await screen.findByRole('heading', { name: 'Ingresar' })).toBeInTheDocument()
  })
})

describe('AppRouter — materias del administrador', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('un Administrador entra a Materias y abre en la pestaña Catálogo', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias')

    expect(await screen.findByRole('tab', { name: 'Catálogo' })).toHaveAttribute('aria-selected', 'true')
    expect(app.pathname).toBe('/materias')
  })

  it('/materias/asignar redirige a la pestaña Asignaciones', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias/asignar')

    await waitFor(() => expect(app.pathname).toBe('/materias'))
    expect(await screen.findByRole('tab', { name: 'Asignaciones' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Catálogo' })).toHaveAttribute('aria-selected', 'false')
  })

  it('la navegación del Administrador solo enlaza Materias a /materias', async () => {
    stubBackend('administrador')

    renderApp('/materias/asignar')

    await screen.findByRole('tab', { name: 'Asignaciones' })

    const hrefs = screen
      .getAllByRole('link', { name: 'Materias' })
      .map((link) => link.getAttribute('href'))
      .filter((href) => href !== null)

    expect(new Set(hrefs)).toEqual(new Set(['/materias']))
    expect(screen.queryByRole('link', { name: 'Asignar materia' })).not.toBeInTheDocument()
  })

  it('la edición de materias ya no tiene ruta: /materias/:id/editar cae en la home del área', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias/10/editar')

    await waitFor(() => expect(app.pathname).toBe('/cuentas'))
    expect(screen.queryByRole('heading', { name: 'Editar materia' })).not.toBeInTheDocument()
  })

  it('un Docente que navega a /materias/asignar vuelve a su lista de materias', async () => {
    stubBackend('docente')

    const app = renderApp('/materias/asignar')

    await waitFor(() => expect(app.pathname).toBe('/materias'))

    expect(screen.queryByRole('tab', { name: 'Asignaciones' })).not.toBeInTheDocument()
  })
})

describe('AppRouter — control de ingreso por área', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it.each([
    ['un Docente', 'docente'],
    ['un Auxiliar', 'auxiliar'],
  ] as const)('%s entra a Control de ingreso', async (_label, account) => {
    stubBackend(account)

    const app = renderApp('/control-ingreso')

    await waitFor(() =>
      expect(
        screen.getByRole('heading', { name: 'Control de ingreso' })
      ).toBeInTheDocument()
    )

    expect(app.pathname).toBe('/control-ingreso')
  })

  it('un Administrador que navega a /control-ingreso es redirigido a la home de su área', async () => {
    stubBackend('administrador')

    const app = renderApp('/control-ingreso')

    await waitFor(() => expect(app.pathname).toBe('/cuentas'))

    expect(
      screen.queryByRole('heading', { name: 'Control de ingreso' })
    ).not.toBeInTheDocument()
  })
})
