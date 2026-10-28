import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { NAVIGATION } from '@/components/layout/navigation'
import { isConnected, visibleSections } from '@/components/layout/navigationSelectors'
import { capabilitiesOf } from '@/features/auth'
import { TOKEN_STORAGE_KEY, meBody, type AccountKey } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'

/**
 * Alcance por clics: toda pantalla enlazada desde el menú lateral de un rol se abre con un clic,
 * el rol no recibe el aviso de falta de permiso (la capacidad que declara la entrada alcanza para
 * la ruta) y la pantalla conserva ese menú, de modo que desde ella se sigue navegando sin escribir
 * una URL.
 *
 * Las pantallas que cuelgan de otras (detalle de curso, de examen, de cuenta, control de ingreso)
 * se alcanzan con los enlaces que cada una prueba en su propio archivo; la tabla completa está
 * en el informe de la rama.
 */

const ROLE_BY_ACCOUNT: Record<AccountKey, 'Docente' | 'Auxiliar' | 'Administrador'> = {
  docente: 'Docente',
  auxiliar: 'Auxiliar',
  administrador: 'Administrador',
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
          meta: { total: 0, total_mias: 0, id_periodo_activo: 1, nombre_periodo_activo: '2/2026' },
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

  const accountKeys = Object.keys(ROLE_BY_ACCOUNT) as AccountKey[]

  for (const accountKey of accountKeys) {
    const sections = visibleSections(capabilitiesOf(ROLE_BY_ACCOUNT[accountKey]))
    const items = sections.flatMap((section) => section.items)
    const linkable = items.filter(isConnected)

    it.each(linkable.map((item) => [item.label, item.to]))(
      `${accountKey}: «%s» abre %s, resalta la entrada y deja el menú disponible`,
      async (label, to) => {
        stubBackend(accountKey)
        const user = userEvent.setup()
        const app = renderApp('/')

        const sidebar = await screen.findByRole('navigation', { name: MAIN_NAV })
        await user.click(within(sidebar).getByRole('button', { name: 'Expandir' }))
        await user.click(within(sidebar).getByRole('link', { name: label }))

        await waitFor(() => expect(app.pathname).toBe(to))
        // La pantalla de destino también trae el menú: no es un callejón sin salida.
        const destination = await screen.findByRole('navigation', { name: MAIN_NAV })

        expect(within(destination).getByRole('link', { name: label })).toHaveAttribute(
          'aria-current',
          'page'
        )
        // La capacidad de la entrada alcanza para la ruta: ProtectedRoute no la rechaza.
        expect(screen.queryByText('Sin permiso')).not.toBeInTheDocument()
      }
    )

    it(`${accountKey}: las entradas sin destino se muestran deshabilitadas y no son enlaces`, async () => {
      stubBackend(accountKey)
      const user = userEvent.setup()
      renderApp('/')

      const sidebar = await screen.findByRole('navigation', { name: MAIN_NAV })
      await user.click(within(sidebar).getByRole('button', { name: 'Expandir' }))

      const pending = items.filter((item) => !isConnected(item))

      expect(pending.length).toBeGreaterThan(0)

      for (const item of pending) {
        expect(within(sidebar).queryByRole('link', { name: item.label })).not.toBeInTheDocument()
        expect(within(sidebar).getByRole('button', { name: item.label })).toHaveAttribute(
          'aria-disabled',
          'true'
        )
      }
    })
  }

  it('toda entrada conectada del catálogo la ve al menos un rol y se prueba arriba', () => {
    const visible = new Set(
      accountKeys.flatMap((accountKey) =>
        visibleSections(capabilitiesOf(ROLE_BY_ACCOUNT[accountKey])).flatMap((section) =>
          section.items.map((item) => item.id)
        )
      )
    )

    for (const item of NAVIGATION.flatMap((section) => section.items)) {
      expect(visible.has(item.id)).toBe(true)
    }
  })
})
