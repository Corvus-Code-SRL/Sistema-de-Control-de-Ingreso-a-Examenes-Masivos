import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { apiClient } from '@/lib/api-client'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import {
  TOKEN_STORAGE_KEY,
  loginBody,
  loginErrors,
  meBody,
  type AccountKey,
} from '@/test/authFixtures'
import { renderApp } from '@/test/renderApp'

/**
 * La sesión dentro de la aplicación completa: bloqueo por sesión expirada, regreso a la ruta
 * anterior, acceso por capacidad y cierre de sesión desde el sidebar.
 */

/** Botón que hace una petición cualquiera con la sesión actual, como lo haría una pantalla. */
function DeadSessionProbe() {
  return <button onClick={() => apiClient('/mis-datos').catch(() => undefined)}>consultar</button>
}

interface BackendOptions {
  session: AccountKey | null
  /** Respuesta de `/mis-datos`, el endpoint con el que la prueba descubre si el token vive. */
  probe?: { status: number; body: unknown }
  onLogin?: (request: RecordedRequest) => { status?: number; body: unknown }
}

function stubBackend({ session, probe, onLogin }: BackendOptions) {
  if (session) {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-inicial')
  }

  return mockApiWith((request) => {
    const { url, method } = request

    if (url.endsWith('/auth/yo')) {
      return session ? { body: meBody(session) } : { status: 401, body: { message: 'Unauthenticated.' } }
    }

    if (url.endsWith('/auth/login') && method === 'POST') {
      return onLogin?.(request) ?? { body: loginBody('docente', 'tok-nuevo') }
    }

    if (url.endsWith('/auth/logout')) {
      return { body: { data: { sesion_cerrada: true } } }
    }

    if (url.endsWith('/mis-datos')) {
      return probe ?? { body: { data: [] } }
    }

    if (url.endsWith('/examenes/formulario')) {
      return { body: { data: { materias: [], ambientes: [], grupos: [] } } }
    }

    if (url.endsWith('/materias')) {
      return { body: { data: [], meta: { total: 0, total_mias: 0, id_periodo_activo: 1 } } }
    }

    return { body: { data: [] } }
  })
}

afterEachCleanup()

function afterEachCleanup() {
  afterEach(() => {
    window.localStorage.clear()
  })
}

describe('sesión expirada', () => {
  it('un 401 en otro endpoint bloquea la pantalla con un aviso y un botón para volver a entrar', async () => {
    const user = userEvent.setup()
    stubBackend({ session: 'docente', probe: { status: 401, body: { message: 'Unauthenticated.' } } })

    renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /exámenes programados|programados/i })

    await user.click(screen.getByRole('button', { name: 'consultar' }))

    const dialog = await screen.findByRole('alertdialog', { name: 'Su sesión expiró' })

    expect(dialog).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeInTheDocument()
    // El token muerto se descarta y la pantalla de fondo queda inerte.
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(document.querySelector('[inert]')).not.toBeNull()
  })

  it('tras volver a entrar, regresa a la ruta en la que estaba', async () => {
    const user = userEvent.setup()
    stubBackend({ session: 'docente', probe: { status: 401, body: { message: 'Unauthenticated.' } } })

    const app = renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /programados/i })
    await user.click(screen.getByRole('button', { name: 'consultar' }))

    await user.click(await screen.findByRole('button', { name: 'Iniciar sesión' }))

    await screen.findByRole('heading', { name: 'Iniciar sesión' })
    expect(app.pathname).toBe('/login')
    // En el propio login el aviso no bloquea: es donde se resuelve.
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()

    await user.type(screen.getByLabelText('Código SIS'), '10452')
    await user.type(screen.getByLabelText('Contraseña'), 'password')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    await waitFor(() => expect(app.pathname).toBe('/examenes/programados'))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-nuevo')
  })

  it('un 401 del login (credenciales incorrectas) no bloquea nada ni cambia de ruta', async () => {
    const user = userEvent.setup()
    stubBackend({ session: null, onLogin: () => loginErrors.credentials })

    const app = renderApp('/login')

    await user.type(await screen.findByLabelText('Código SIS'), '10452')
    await user.type(screen.getByLabelText('Contraseña'), 'mala')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    await screen.findByText('Código SIS o contraseña incorrectos.')

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(app.pathname).toBe('/login')
    expect(document.querySelector('[inert]')).toBeNull()
  })

  it('un 401 sin sesión no bloquea la app: sin sesión todo sigue funcionando', async () => {
    const user = userEvent.setup()
    stubBackend({ session: null, probe: { status: 401, body: { message: 'Unauthenticated.' } } })

    renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /programados/i })

    await user.click(screen.getByRole('button', { name: 'consultar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('un 403 se muestra como permiso negado en su lugar y no cierra la sesión', async () => {
    const user = userEvent.setup()
    stubBackend({ session: 'docente', probe: { status: 403, body: { message: 'Sin permiso.' } } })

    renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /programados/i })

    await user.click(screen.getByRole('button', { name: 'consultar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-inicial')
    expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument()
  })
})

describe('acceso por capacidad', () => {
  it('sin sesión deja pasar a todas las pantallas del área Docente', async () => {
    stubBackend({ session: null })

    const app = renderApp('/examenes/nuevo')

    await waitFor(() => expect(app.pathname).toBe('/examenes/nuevo'))
    expect(screen.queryByText('Sin permiso')).not.toBeInTheDocument()
  })

  it('un Docente abre la pantalla de exámenes', async () => {
    stubBackend({ session: 'docente' })

    const app = renderApp('/examenes/programados')

    await screen.findByRole('heading', { name: /programados/i })
    expect(app.pathname).toBe('/examenes/programados')
  })

  it('un Auxiliar no tiene la capacidad de gestionar exámenes: ve el aviso y conserva la sesión', async () => {
    stubBackend({ session: 'auxiliar' })

    const app = renderApp('/examenes/nuevo')

    expect(await screen.findByRole('alert')).toHaveTextContent('Su rol no tiene permiso')
    expect(app.pathname).toBe('/examenes/nuevo')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-inicial')
    expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument()
  })

  it('un Administrador cae en su propia área aunque abra una URL del Docente', async () => {
    stubBackend({ session: 'administrador' })

    const app = renderApp('/examenes/nuevo')

    await waitFor(() => expect(app.pathname).toBe('/cuentas'))
  })
})

describe('cuenta en el sidebar', () => {
  it('sin sesión ofrece iniciar sesión, no cerrarla', async () => {
    stubBackend({ session: null })

    renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: /programados/i })

    expect(screen.getAllByRole('link', { name: 'Iniciar sesión' }).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeInTheDocument()
  })

  it('cerrar sesión llama al logout con el token, limpia la sesión y lleva al login', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({ session: 'docente' })

    const app = renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: /programados/i })

    await user.click(screen.getAllByRole('button', { name: 'Cerrar sesión' })[0])

    await screen.findByRole('heading', { name: 'Iniciar sesión' })

    const logout = calls.find((call) => call.url.endsWith('/auth/logout'))

    expect(logout?.headers.Authorization).toBe('Bearer tok-inicial')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(app.pathname).toBe('/login')
  })

  it('no queda ningún selector de rol de desarrollo', async () => {
    stubBackend({ session: null })

    renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: /programados/i })

    expect(screen.queryByText('Vista de desarrollo')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})
