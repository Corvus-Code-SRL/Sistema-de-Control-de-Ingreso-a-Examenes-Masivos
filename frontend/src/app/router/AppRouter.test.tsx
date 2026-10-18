import { screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { detailOf, faultTypes, records } from '@/test/riskCenterFixtures'
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

  it('/materias/asignaciones abre la vista de Asignaciones del Administrador', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias/asignaciones')

    expect(await screen.findByRole('heading', { name: 'Asignaciones de materias' })).toBeInTheDocument()
    expect(app.pathname).toBe('/materias/asignaciones')
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })

  it('/materias/catalogo abre la vista del catálogo, sin pestañas', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias/catalogo')

    expect(await screen.findByRole('heading', { name: 'Catálogo de materias' })).toBeInTheDocument()
    expect(app.pathname).toBe('/materias/catalogo')
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })

  it.each([['/materias'], ['/materias?tab=catalogo'], ['/materias?tab=asignaciones'], ['/materias/asignar']])(
    'el Administrador que abre %s llega a Asignaciones',
    async (route) => {
      stubBackend('administrador')

      const app = renderApp(route)

      await waitFor(() => expect(app.pathname).toBe('/materias/asignaciones'))
      expect(await screen.findByRole('heading', { name: 'Asignaciones de materias' })).toBeInTheDocument()
    }
  )

  it('la redirección conserva ?carrera= y descarta ?tab=', async () => {
    stubBackend('administrador')

    const app = renderApp('/materias?tab=asignaciones&carrera=4')

    await waitFor(() => expect(app.pathname).toBe('/materias/asignaciones'))
    expect(await screen.findByRole('heading', { name: 'Asignaciones de materias' })).toBeInTheDocument()
  })

  it('la navegación del Administrador no enlaza /materias: Materias es solo un encabezado', async () => {
    stubBackend('administrador')

    renderApp('/materias/asignaciones')

    await screen.findByRole('heading', { name: 'Asignaciones de materias' })

    const menu = within(screen.getByRole('navigation', { name: 'Navegación principal' }))

    expect(menu.queryByRole('link', { name: 'Materias' })).not.toBeInTheDocument()
    expect(menu.getByRole('link', { name: 'Asignaciones' })).toHaveAttribute('href', '/materias/asignaciones')
    expect(menu.getByRole('link', { name: 'Catálogo' })).toHaveAttribute('href', '/materias/catalogo')
    expect(menu.queryByRole('link', { name: 'Asignar materia' })).not.toBeInTheDocument()
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

    expect(screen.queryByRole('heading', { name: 'Asignaciones de materias' })).not.toBeInTheDocument()
  })

  it.each([['/materias/asignaciones'], ['/materias/catalogo']])(
    'un Docente que navega a %s vuelve a su lista de materias',
    async (route) => {
      stubBackend('docente')

      const app = renderApp(route)

      await waitFor(() => expect(app.pathname).toBe('/materias'))
      expect(screen.queryByRole('heading', { name: 'Catálogo de materias' })).not.toBeInTheDocument()
    }
  )
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

describe('AppRouter — Central de Riesgo por área', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  /** Sesión de la cuenta indicada y una Central de Riesgo vacía, salvo el detalle 9945. */
  function stubRiskCenter(session: AccountKey | null) {
    if (session) {
      window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
    }

    return mockApiWith(({ url }) => {
      if (url.endsWith('/auth/yo')) {
        return session ? { body: meBody(session) } : { status: 401, body: { message: 'Unauthenticated.' } }
      }

      if (/\/central-riesgo\/\d+$/.test(url)) {
        return { body: { data: detailOf(9945) } }
      }

      if (url.includes('/central-riesgo')) {
        return {
          body: { data: records, meta: { current_page: 1, last_page: 1, per_page: 10, total: records.length } },
        }
      }

      if (url.endsWith('/tipos-falta')) {
        return { body: { data: faultTypes } }
      }

      return { body: { data: [] } }
    })
  }

  it.each([
    ['un Docente', 'docente'],
    ['un Administrador', 'administrador'],
  ] as const)('%s entra a la Central de Riesgo', async (_label, account) => {
    stubRiskCenter(account)

    const app = renderApp('/central-riesgo')

    expect(await screen.findByRole('heading', { name: 'Central de Riesgo' })).toBeInTheDocument()
    expect(app.pathname).toBe('/central-riesgo')
    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('un Auxiliar que navega a /central-riesgo vuelve a la home de su área', async () => {
    stubRiskCenter('auxiliar')

    const app = renderApp('/central-riesgo')

    await waitFor(() => expect(app.pathname).toBe('/mis-examenes'))
    expect(screen.queryByRole('heading', { name: 'Central de Riesgo' })).not.toBeInTheDocument()
  })

  it('un Auxiliar tampoco abre un detalle por enlace directo', async () => {
    stubRiskCenter('auxiliar')

    const app = renderApp('/central-riesgo/9945')

    await waitFor(() => expect(app.pathname).toBe('/mis-examenes'))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('sin sesión la Central de Riesgo lleva al login', async () => {
    stubRiskCenter(null)

    const app = renderApp('/central-riesgo')

    await waitFor(() => expect(app.pathname).toBe('/login'))
  })

  it('un enlace directo al detalle abre el listado con el panel', async () => {
    stubRiskCenter('administrador')

    const app = renderApp('/central-riesgo/9945')

    expect(await screen.findByRole('dialog', { name: 'Registro de la Central de Riesgo' })).toBeInTheDocument()
    expect(app.pathname).toBe('/central-riesgo/9945')
  })

  it('el Docente llega desde «Registros» y el Administrador desde «Supervisión › Central de riesgo»', async () => {
    stubRiskCenter('docente')
    const docente = renderApp('/materias')

    expect(await screen.findByRole('link', { name: 'Registros' })).toHaveAttribute('href', '/central-riesgo')

    docente.unmount()
    stubRiskCenter('administrador')
    renderApp('/cuentas')

    expect(await screen.findByRole('link', { name: 'Central de riesgo' })).toHaveAttribute('href', '/central-riesgo')
    expect(screen.getByText('Supervisión')).toBeInTheDocument()
  })
})
