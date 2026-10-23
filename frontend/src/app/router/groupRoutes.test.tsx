import userEvent from '@testing-library/user-event'
import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { TOKEN_STORAGE_KEY, meBody } from '@/test/authFixtures'
import { groupDetailResponse, makeGroup, makeSubject } from '@/test/fixtures'
import { groupStudentsResponse, makeGroupStudent } from '@/test/groupStudentFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'

/** La app completa arranca la sesión antes de la vista: bajo carga tarda más que la espera por defecto. */
const BOOT = { timeout: 4000 }

/** Rutas del detalle del grupo (HU-020) y su carga de nómina, con la sesión de un Docente. */
function stubBackend() {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url }) => {
    const { pathname, searchParams } = new URL(url, 'http://localhost')

    if (pathname.endsWith('/auth/yo')) return { body: meBody('docente') }

    if (/\/grupos\/\d+\/estudiantes$/.test(pathname)) {
      return { body: groupStudentsResponse([makeGroupStudent()], searchParams.get('q') ?? '') }
    }

    if (/\/grupos\/\d+$/.test(pathname)) {
      return { body: groupDetailResponse(makeGroup(), makeSubject()) }
    }

    return { body: { data: [] } }
  })
}

describe('AppRouter — detalle del grupo', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('un Docente abre /grupos/:id', async () => {
    stubBackend()

    const app = renderApp('/grupos/100')

    expect(
      await screen.findByRole('heading', { name: 'Estudiantes inscritos' }, BOOT)
    ).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100')
  })

  it('el enlace viejo /cursos/:id redirige a /grupos/:id y conserva la búsqueda', async () => {
    stubBackend()

    const app = renderApp('/cursos/100?q=adriana')

    await waitFor(() => expect(app.pathname).toBe('/grupos/100'), BOOT)
    expect(await screen.findByRole('searchbox', {}, BOOT)).toHaveValue('adriana')
  })

  it('la edición del grupo tiene su propia URL', async () => {
    stubBackend()

    const app = renderApp('/grupos/100/editar')

    expect(await screen.findByLabelText(/N° de grupo/i, {}, BOOT)).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Editar grupo' })).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100/editar')
  })

  it('la carga de nómina tiene su propia URL', async () => {
    stubBackend()

    const app = renderApp('/grupos/100/nomina')

    expect(await screen.findByText('Carga de nómina', {}, BOOT)).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100/nomina')
  })
})

describe('AppRouter — navegación contextual', () => {
  afterEach(() => window.localStorage.clear())

  it.each([
    ['Ver exámenes', '/grupos/100/examenes', 'Exámenes del grupo'],
    ['Auxiliares', '/grupos/100/auxiliares', 'Auxiliares del grupo'],
  ])('%s abre su página independiente y permite regresar', async (action, destination, title) => {
    stubBackend()
    const app = renderApp('/grupos/100')
    await userEvent.click(await screen.findByRole('link', { name: action }, BOOT))
    await waitFor(() => {
      expect(screen.queryByRole('status')).not.toBeInTheDocument()
      expect(screen.getByRole('heading', { name: title })).toBeInTheDocument()
    }, BOOT)
    expect(app.pathname).toBe(destination)
    await userEvent.click(screen.getByRole('link', { name: 'Volver al grupo' }))
    expect(await screen.findByRole('heading', { name: 'Estudiantes inscritos' }, BOOT)).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100')
  })

  it.each([
    ['/grupos/100/examenes', 'Exámenes del grupo'],
    ['/grupos/100/auxiliares', 'Auxiliares del grupo'],
  ])('abre directamente %s', async (route, title) => {
    stubBackend()
    const app = renderApp(route)
    await waitFor(() => {
      expect(screen.queryByRole('status')).not.toBeInTheDocument()
      expect(screen.getByRole('heading', { name: title })).toBeInTheDocument()
    }, BOOT)
    expect(app.pathname).toBe(route)
  })
})
