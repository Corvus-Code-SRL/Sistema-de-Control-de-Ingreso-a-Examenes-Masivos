import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { navigationByArea } from '@/components/layout/navigation'
import type { Area } from '@/features/auth'
import { TOKEN_STORAGE_KEY, meBody, type AccountKey } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'

/**
 * Alcance por clics: toda pantalla enlazada desde el menú lateral de un rol se abre con un clic
 * y conserva ese menú, de modo que desde ella se sigue navegando sin escribir una URL.
 *
 * Las pantallas que cuelgan de otras (detalle de curso, de examen, de cuenta, control de ingreso)
 * se alcanzan con los enlaces que cada una prueba en su propio archivo; la tabla completa está
 * en el informe de la rama.
 */

const SESSION_BY_AREA: Record<Area, AccountKey> = {
  docente: 'docente',
  auxiliar: 'auxiliar',
  administrador: 'administrador',
}

function stubBackend(session: AccountKey) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      return { body: meBody(session) }
    }

    if (url.includes('/materias') && !url.includes('/carreras/')) {
      return {
        body: {
          data: [],
          meta: { total: 0, total_mias: 0, id_periodo_activo: 1, nombre_periodo_activo: '2-2026' },
        },
      }
    }

    if (url.endsWith('/examenes/formulario')) {
      return { body: { data: { materias: [], ambientes: [], grupos: [] } } }
    }

    return { body: { data: [], meta: { id_periodo_activo: 1, total: 0, total_mios: 0 } } }
  })
}

const MAIN_NAV = 'Navegación principal'

describe('alcance por clics desde el menú lateral', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  const areas = Object.keys(navigationByArea) as Area[]

  for (const area of areas) {
    const linkable = navigationByArea[area]
      .flatMap((group) => group.items)
      .filter((item) => item.to)

    it.each(linkable.map((item) => [item.label, item.to as string]))(
      `${area}: «%s» abre %s y deja el menú disponible`,
      async (label, to) => {
        stubBackend(SESSION_BY_AREA[area])
        const user = userEvent.setup()
        const app = renderApp('/')

        const sidebar = await screen.findByRole('navigation', { name: MAIN_NAV })
        await user.click(within(sidebar).getByRole('link', { name: label }))

        await waitFor(() => expect(app.pathname).toBe(to))
        // La pantalla de destino también trae el menú: no es un callejón sin salida.
        expect(await screen.findByRole('navigation', { name: MAIN_NAV })).toBeInTheDocument()
      }
    )
  }

  it('docente: los ítems sin destino se muestran deshabilitados y no son enlaces', async () => {
    stubBackend('docente')
    renderApp('/')

    const sidebar = await screen.findByRole('navigation', { name: MAIN_NAV })
    const pending = navigationByArea.docente
      .flatMap((group) => group.items)
      .filter((item) => !item.to)

    expect(pending.length).toBeGreaterThan(0)

    for (const item of pending) {
      expect(within(sidebar).queryByRole('link', { name: item.label })).not.toBeInTheDocument()
      expect(within(sidebar).getByText(item.label).closest('[aria-disabled="true"]')).not.toBeNull()
    }
  })
})
