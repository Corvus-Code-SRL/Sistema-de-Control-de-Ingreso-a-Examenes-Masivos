import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'

import {
  entries,
  options,
} from '@/test/auditLogFixtures'

import { mockApiWith } from '@/test/http'

import {
  TOKEN_STORAGE_KEY,
  meBody,
  type AccountKey,
} from '@/test/authFixtures'

import { renderApp } from '@/test/renderApp'
import { openAllSections } from '@/test/sidebar'

const REPORT_PATH = '/reportes/bitacora'
const TITLE = 'Generar reporte de bitácora'

const REPORT_URL =
  `${REPORT_PATH}?desde=2026-10-01&hasta=2026-10-31`

function stubReport(session: AccountKey | null) {
  if (session) {
    window.localStorage.setItem(
      TOKEN_STORAGE_KEY,
      'tok'
    )
  }

  return mockApiWith(({ url }) => {
    const parsed = new URL(url)
    const path = parsed.pathname.replace(/^.*\/api/, '')

    if (path === '/auth/yo') {
      return session
        ? { body: meBody(session) }
        : {
            status: 401,
            body: { message: 'Unauthenticated.' },
          }
    }

    if (path === '/bitacora/opciones') {
      return { body: { data: options } }
    }

    if (path === REPORT_PATH) {
      return {
        body: {
          data: entries,

          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: entries.length,
          },

          totales: {
            total_operaciones: entries.length,
            total_usuarios: new Set(
              entries.map(
                (entry) => entry.usuario.id_usuario
              )
            ).size,
            por_accion: [],
          },

          filtros: {
            desde: parsed.searchParams.get('desde'),
            hasta: parsed.searchParams.get('hasta'),
            q: parsed.searchParams.get('q'),
            accion: parsed.searchParams.get('accion'),
            modulo: parsed.searchParams.get('modulo'),
          },

          puede_exportar: entries.length > 0,
        },
      }
    }

    if (path === '/materias') {
      return {
        body: {
          data: [],
          meta: {
            total: 0,
            total_mias: 0,
            id_periodo_activo: 1,
          },
        },
      }
    }

    if (path === '/examenes/cronometros') {
      return {
        body: {
          data: {
            examenes: [],
            hora_servidor_en: '2026-11-17T18:22:00Z',
          },
        },
      }
    }

    return { body: { data: [] } }
  })
}

afterEach(() => {
  window.localStorage.clear()
  vi.unstubAllGlobals()
})

describe('AppRouter — Reporte de bitacora HU-068', () => {
  it('permite al administrador acceder al reporte', async () => {
    stubReport('administrador')

    const app = renderApp(REPORT_URL)

    expect(
      await screen.findByRole('heading', {
        name: TITLE,
      })
    ).toBeInTheDocument()

    expect(app.pathname).toBe(REPORT_PATH)

    expect(
      await screen.findByRole('table')
    ).toBeInTheDocument()
  })

  it.each([
    ['Docente', 'docente'],
    ['Auxiliar', 'auxiliar'],
  ] as const)(
    'restringe el acceso al %s sin permisos',
    async (_label, account) => {
      const api = stubReport(account)

      renderApp(REPORT_URL)

      expect(
        await screen.findByText(
          'No tiene permiso para ver esta sección.'
        )
      ).toBeInTheDocument()

      expect(
        screen.queryByRole('heading', {
          name: TITLE,
        })
      ).not.toBeInTheDocument()

      expect(
        screen.queryByRole('table')
      ).not.toBeInTheDocument()

      expect(
        api.calls.some((call) =>
          call.url.includes(REPORT_PATH)
        )
      ).toBe(false)
    }
  )

  it('redirige al login cuando no existe sesion', async () => {
    const api = stubReport(null)

    const app = renderApp(REPORT_URL)

    await waitFor(() => {
      expect(app.pathname).toBe('/login')
    })

    expect(
      await screen.findByRole('heading', {
        name: 'Ingresar',
      })
    ).toBeInTheDocument()

    expect(
      api.calls.some((call) =>
        call.url.includes(REPORT_PATH)
      )
    ).toBe(false)
  })

  it('muestra el enlace del reporte al administrador', async () => {
    const user = userEvent.setup()

    stubReport('administrador')

    renderApp('/cuentas')

    await openAllSections(
      user,
      await screen.findByRole('navigation', {
        name: 'Navegación principal',
      })
    )

    expect(
      screen.getByRole('link', {
        name: TITLE,
      })
    ).toHaveAttribute('href', REPORT_PATH)
  })

  it.each([
    ['docente', '/materias'],
    ['auxiliar', '/mis-examenes'],
  ] as const)(
    'oculta el enlace del reporte al %s',
    async (account, route) => {
      const user = userEvent.setup()

      stubReport(account)

      renderApp(route)

      await openAllSections(
        user,
        await screen.findByRole('navigation', {
          name: 'Navegación principal',
        })
      )

      expect(
        screen.queryByRole('link', {
          name: TITLE,
        })
      ).not.toBeInTheDocument()
    }
  )
})