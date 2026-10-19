import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { TOKEN_STORAGE_KEY, meBody } from '@/test/authFixtures'
import { groupDetailResponse, makeGroup, makeSubject } from '@/test/fixtures'
import { groupStudentsResponse, makeGroupStudent } from '@/test/groupStudentFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'

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

    expect(await screen.findByRole('heading', { name: 'Estudiantes inscritos' })).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100')
  })

  it('el enlace viejo /cursos/:id redirige a /grupos/:id y conserva la búsqueda', async () => {
    stubBackend()

    const app = renderApp('/cursos/100?q=adriana')

    await waitFor(() => expect(app.pathname).toBe('/grupos/100'))
    expect(await screen.findByRole('searchbox')).toHaveValue('adriana')
  })

  it('la carga de nómina tiene su propia URL', async () => {
    stubBackend()

    const app = renderApp('/grupos/100/nomina')

    expect(await screen.findByText('Carga de nómina')).toBeInTheDocument()
    expect(app.pathname).toBe('/grupos/100/nomina')
  })
})
