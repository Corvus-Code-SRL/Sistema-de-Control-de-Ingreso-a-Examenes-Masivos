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
 * La sesión dentro de la aplicación completa: redirección al login por sesión expirada, regreso a la
 * ruta anterior, acceso por capacidad y cierre de sesión desde el sidebar.
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
  it('un 401 en otro endpoint lleva al login con el aviso «Su sesión expiró»', async () => {
    const user = userEvent.setup()
    stubBackend({ session: 'docente', probe: { status: 401, body: { message: 'Unauthenticated.' } } })

    const app = renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /exámenes programados|programados/i })

    await user.click(screen.getByRole('button', { name: 'consultar' }))

    await waitFor(() => expect(app.pathname).toBe('/login'))

    const notice = await screen.findByRole('status')

    expect(notice).toHaveAttribute('data-notice', 'sesion-expirada')
    expect(notice).toHaveTextContent('Su sesión expiró')
    // Ya no hay diálogo que bloquee: se redirige, y el token muerto se descarta.
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(document.querySelector('[inert]')).toBeNull()
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
  })

  it('tras volver a entrar, regresa a la ruta en la que estaba', async () => {
    const user = userEvent.setup()
    stubBackend({ session: 'docente', probe: { status: 401, body: { message: 'Unauthenticated.' } } })

    const app = renderApp('/examenes/programados', <DeadSessionProbe />)
    await screen.findByRole('heading', { name: /programados/i })
    await user.click(screen.getByRole('button', { name: 'consultar' }))

    await screen.findByRole('heading', { name: 'Ingresar' })
    expect(app.pathname).toBe('/login')

    await user.type(screen.getByLabelText(/^Código SIS/), '10452')
    await user.type(screen.getByLabelText(/^Contraseña/), 'password')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    await waitFor(() => expect(app.pathname).toBe('/examenes/programados'))
    expect(screen.queryByText('Su sesión expiró')).not.toBeInTheDocument()
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-nuevo')
  })

  it('un 401 del login (credenciales incorrectas) no cambia de ruta ni anuncia una sesión expirada', async () => {
    const user = userEvent.setup()
    stubBackend({ session: null, onLogin: () => loginErrors.credentials })

    const app = renderApp('/login')

    await user.type(await screen.findByLabelText(/^Código SIS/), '10452')
    await user.type(screen.getByLabelText(/^Contraseña/), 'mala')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    await screen.findByText('Código SIS o contraseña incorrectos')

    expect(screen.queryByText('Su sesión expiró')).not.toBeInTheDocument()
    expect(app.pathname).toBe('/login')
  })

  it('sin sesión, una ruta protegida lleva al login sin aviso de expiración y recuerda la ruta', async () => {
    const user = userEvent.setup()
    // El servidor aceptará el login y /yo responderá; pero no hay token guardado: la app arranca anónima.
    stubBackend({ session: 'docente' })
    window.localStorage.removeItem(TOKEN_STORAGE_KEY)

    const app = renderApp('/examenes/programados')

    await screen.findByRole('heading', { name: 'Ingresar' })
    expect(app.pathname).toBe('/login')
    expect(screen.queryByText('Su sesión expiró')).not.toBeInTheDocument()

    await user.type(screen.getByLabelText(/^Código SIS/), '10452')
    await user.type(screen.getByLabelText(/^Contraseña/), 'password')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    await waitFor(() => expect(app.pathname).toBe('/examenes/programados'))
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
  it('sin sesión ninguna pantalla del área Docente se abre: todas llevan al login', async () => {
    stubBackend({ session: null })

    const app = renderApp('/examenes/nuevo')

    await waitFor(() => expect(app.pathname).toBe('/login'))
    expect(screen.queryByText('Sin permiso')).not.toBeInTheDocument()
  })

  it('un Docente abre la pantalla de exámenes', async () => {
    stubBackend({ session: 'docente' })

    const app = renderApp('/examenes/programados')

    await screen.findByRole('heading', { name: /programados/i })
    expect(app.pathname).toBe('/examenes/programados')
  })

  it('un Auxiliar cae en su propia área aunque abra una URL del Docente y conserva la sesión', async () => {
    stubBackend({ session: 'auxiliar' })

    const app = renderApp('/examenes/nuevo')

    await waitFor(() => expect(app.pathname).toBe('/mis-examenes'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
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
  it('sin sesión no hay sidebar: solo el formulario de ingreso', async () => {
    stubBackend({ session: null })

    renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: 'Ingresar' })

    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeInTheDocument()
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })

  it('cerrar sesión llama al logout con el token, limpia la sesión y lleva al login', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({ session: 'docente' })

    const app = renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: /programados/i })

    await user.click(screen.getAllByRole('button', { name: 'Cerrar sesión' })[0])

    await screen.findByRole('heading', { name: 'Ingresar' })

    const logout = calls.find((call) => call.url.endsWith('/auth/logout'))

    expect(logout?.headers.Authorization).toBe('Bearer tok-inicial')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(app.pathname).toBe('/login')
  })

  it('no queda ningún selector de rol de desarrollo', async () => {
    stubBackend({ session: 'docente' })

    renderApp('/examenes/programados')
    await screen.findByRole('heading', { name: /programados/i })

    expect(screen.queryByText('Vista de desarrollo')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})

/**
 * Después de iniciar sesión cada rol va a la entrada que ya tiene: los inicios /docente/inicio,
 * /auxiliar/inicio y /admin/inicio del diseño no existen todavía. El rol sale de /auth/yo.
 */
describe('redirección después de iniciar sesión', () => {
  function stubLoginServer(me: AccountKey) {
    mockApiWith(({ url, method }) => {
      if (url.endsWith('/auth/yo')) return { body: meBody(me) }
      if (url.endsWith('/auth/login') && method === 'POST') return { body: loginBody(me) }
      if (url.endsWith('/usuarios')) return { body: { data: [], meta: { id_usuario_actual: 'x' } } }
      if (url.endsWith('/materias')) {
        return { body: { data: [], meta: { total: 0, total_mias: 0, id_periodo_activo: 1 } } }
      }

      return { body: { data: [] } }
    })
  }

  async function loginAs(sis: string) {
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText(/^Código SIS/), sis)
    await user.type(screen.getByLabelText(/^Contraseña/), 'password')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))
  }

  it.each<[string, AccountKey, string]>([
    ['10452', 'docente', '/materias'],
    ['ADM0001', 'administrador', '/cuentas'],
    ['201800451', 'auxiliar', '/mis-examenes'],
  ])('%s (%s) llega a %s', async (sis, account, destination) => {
    stubLoginServer(account)

    const app = renderApp('/login')

    await loginAs(sis)

    await waitFor(() => expect(app.pathname).toBe(destination))
  })

  it('el Auxiliar llega a su pantalla propia y no ve el aviso de permiso', async () => {
    stubLoginServer('auxiliar')

    const app = renderApp('/login')
    await loginAs('201800451')

    await waitFor(() => expect(app.pathname).toBe('/mis-examenes'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('el destino sale del rol que devuelve /yo, no del formato del código', async () => {
    // Un código de formato Docente (5 dígitos) cuyo /yo dice Administrador.
    stubLoginServer('administrador')

    const app = renderApp('/login')

    await loginAs('10452')

    await waitFor(() => expect(app.pathname).toBe('/cuentas'))
  })

  it('quien ya tiene sesión y abre /login vuelve a la entrada de su rol', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
    stubLoginServer('administrador')

    const app = renderApp('/login')

    await waitFor(() => expect(app.pathname).toBe('/cuentas'))
  })
})
