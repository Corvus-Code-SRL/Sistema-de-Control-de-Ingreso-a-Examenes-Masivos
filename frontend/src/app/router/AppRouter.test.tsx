import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { TOKEN_STORAGE_KEY, meBody, type AccountKey } from '@/test/authFixtures'
import { renderApp } from '@/test/renderApp'

/**
 * Las áreas salen del rol de la sesión. Sin sesión, la app es el área Docente, como siempre.
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

  it('sin sesión la app es el área Docente y no exige iniciar sesión', async () => {
    stubBackend(null)

    const app = renderApp('/ambientes')

    await waitFor(() => expect(app.pathname).toBe('/materias'))
    expect(screen.queryByRole('heading', { name: 'Iniciar sesión' })).not.toBeInTheDocument()

  })
})

describe('AppRouter — asignación de materias a carreras', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('un Administrador entra a la asignación de materias', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias/asignar')

    expect(
      await screen.findByRole('heading', {
        name: 'Asignar materia a carrera',
      })
    ).toBeInTheDocument()

    expect(app.pathname).toBe('/materias/asignar')
  })

  it('el Administrador tiene acceso a la asignación desde su navegación', async () => {
    stubBackend('administrador')

    renderApp('/materias/asignar')

    await screen.findByRole('heading', {
      name: 'Asignar materia a carrera',
    })

    const links = screen.getAllByRole('link', {
      name: 'Asignar materia',
    })

    expect(
      links.some(
        (link) => link.getAttribute('href') === '/materias/asignar'
      )
    ).toBe(true)
  })

  it('un Docente no puede entrar a la asignación administrativa', async () => {
    stubBackend('docente')

    const app = renderApp('/materias/asignar')

    await waitFor(() => expect(app.pathname).toBe('/materias'))

    expect(
      screen.queryByRole('heading', {
        name: 'Asignar materia a carrera',
      })
    ).not.toBeInTheDocument()
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
