import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useNavigate } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import { apiClient } from '@/lib/api-client'
import { mockApiWith } from '@/test/http'
import {
  TOKEN_STORAGE_KEY,
  loginBody,
  loginErrors,
  meBody,
  passwordChangeRequired,
  type AccountKey,
} from '@/test/authFixtures'
import { renderApp } from '@/test/renderApp'

/**
 * HU-001: la cuenta que entra con la contraseña temporal solo puede cambiarla.
 *
 * El «servidor» de la prueba recuerda si el cambio sigue pendiente y rechaza con 403
 * `cambio_de_clave_pendiente` toda ruta que no sea /auth/yo, logout o cambiar-contrasenia.
 */

const TEMPORARY_PASSWORD = 'Temporal2026'
const NEW_PASSWORD = 'ClaveNueva2026'

const SIS_BY_ACCOUNT: Record<AccountKey, string> = {
  administrador: 'ADM0001',
  docente: '10452',
  auxiliar: '201800451',
}

function ProbeButton() {
  return <button onClick={() => apiClient('/mis-datos').catch(() => undefined)}>consultar</button>
}

/** Intenta abrir otra pantalla desde dentro de la app, como lo haría un enlace o el historial. */
function GoTo({ to }: { to: string }) {
  const navigate = useNavigate()

  return <button onClick={() => navigate(to)}>ir a {to}</button>
}

interface BackendOptions {
  account: AccountKey
  /** El servidor ya tiene la contraseña temporal pendiente de cambio. */
  mustChange: boolean
  /** Hay un token guardado: la app arranca con la sesión ya abierta. */
  hasSession?: boolean
}

function stubBackend({ account, mustChange, hasSession = false }: BackendOptions) {
  const state = { mustChange, password: TEMPORARY_PASSWORD }

  if (hasSession) {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-inicial')
  }

  const backend = mockApiWith(({ url, method, body }) => {
    const payload = (body ?? {}) as Record<string, string>

    if (url.endsWith('/auth/yo')) {
      return { body: meBody(account, state.mustChange) }
    }

    if (url.endsWith('/auth/login') && method === 'POST') {
      return payload.password === state.password
        ? { body: loginBody(account, 'tok-nuevo', state.mustChange) }
        : loginErrors.credentials
    }

    if (url.endsWith('/auth/logout')) {
      return { body: { data: { sesion_cerrada: true } } }
    }

    if (url.endsWith('/auth/cambiar-contrasenia') && method === 'POST') {
      if (payload.contrasenia_actual !== state.password) {
        return {
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { contrasenia_actual: ['La contraseña actual es incorrecta.'] },
          },
        }
      }

      state.mustChange = false
      state.password = payload.contrasenia_nueva

      return {
        body: { data: { debe_cambiar_contrasenia: false }, mensaje: 'Contraseña actualizada correctamente.' },
      }
    }

    if (state.mustChange) {
      return passwordChangeRequired
    }

    if (url.endsWith('/usuarios')) {
      return { body: { data: [], meta: { id_usuario_actual: null } } }
    }

    if (url.endsWith('/materias')) {
      return { body: { data: [], meta: { total: 0, total_mias: 0, id_periodo_activo: 1 } } }
    }

    return { body: { data: [] } }
  })

  return { ...backend, state }
}

async function fillChangeForm(current: string, next: string, confirmation = next) {
  const user = userEvent.setup()

  await user.type(await screen.findByLabelText(/^contraseña temporal/i), current)
  await user.type(screen.getByLabelText(/^contraseña nueva/i), next)
  await user.type(screen.getByLabelText(/^confirmar contraseña nueva/i), confirmation)
  await user.click(screen.getByRole('button', { name: 'Cambiar contraseña' }))
}

async function logIn(sis: string, password: string) {
  const user = userEvent.setup()

  await user.type(await screen.findByLabelText(/^Código SIS/), sis)
  await user.type(screen.getByLabelText(/^Contraseña/), password)
  await user.click(screen.getByRole('button', { name: 'Ingresar' }))
}

afterEach(() => {
  window.localStorage.clear()
})

describe('ingreso con la contraseña temporal', () => {
  it('tras iniciar sesión aterriza en «Cambiar contraseña»', async () => {
    stubBackend({ account: 'docente', mustChange: true })

    const app = renderApp('/login')

    await logIn(SIS_BY_ACCOUNT.docente, TEMPORARY_PASSWORD)

    expect(await screen.findByRole('heading', { name: 'Cambiar contraseña' })).toBeInTheDocument()
    expect(app.pathname).toBe('/cambiar-contrasena')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-nuevo')
  })

  it('aterriza ahí aunque haya llegado pidiendo otra ruta protegida', async () => {
    stubBackend({ account: 'docente', mustChange: true })

    const app = renderApp('/examenes/programados')

    await screen.findByRole('heading', { name: 'Ingresar' })
    await logIn(SIS_BY_ACCOUNT.docente, TEMPORARY_PASSWORD)

    await screen.findByRole('heading', { name: 'Cambiar contraseña' })
    expect(app.pathname).toBe('/cambiar-contrasena')
  })

  it('con la sesión ya abierta, recargar cualquier ruta lleva a «Cambiar contraseña»', async () => {
    stubBackend({ account: 'administrador', mustChange: true, hasSession: true })

    const app = renderApp('/cuentas')

    await screen.findByRole('heading', { name: 'Cambiar contraseña' })
    expect(app.pathname).toBe('/cambiar-contrasena')
  })

  it('no muestra el menú ni deja abrir otra pantalla, ni siquiera /login', async () => {
    const user = userEvent.setup()
    stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias', (
      <>
        <GoTo to="/examenes/programados" />
        <GoTo to="/login" />
      </>
    ))

    await screen.findByRole('heading', { name: 'Cambiar contraseña' })

    expect(screen.queryByRole('navigation', { name: 'Navegación principal' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'ir a /examenes/programados' }))
    await waitFor(() => expect(app.pathname).toBe('/cambiar-contrasena'))

    await user.click(screen.getByRole('button', { name: 'ir a /login' }))
    await waitFor(() => expect(app.pathname).toBe('/cambiar-contrasena'))
    expect(screen.getByRole('heading', { name: 'Cambiar contraseña' })).toBeInTheDocument()
  })

  it('un 403 cambio_de_clave_pendiente en cualquier petición lleva a «Cambiar contraseña» sin cerrar la sesión', async () => {
    const user = userEvent.setup()
    // /yo aún no lo sabe: el indicador se enciende en el servidor con la sesión ya abierta.
    const backend = stubBackend({ account: 'docente', mustChange: false, hasSession: true })

    const app = renderApp('/examenes/programados', <ProbeButton />)
    await screen.findByRole('heading', { name: /programados/i })

    backend.state.mustChange = true
    await user.click(screen.getByRole('button', { name: 'consultar' }))

    await screen.findByRole('heading', { name: 'Cambiar contraseña' })
    expect(app.pathname).toBe('/cambiar-contrasena')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-inicial')
  })
})

describe('pantalla «Cambiar contraseña»', () => {
  it('rechaza repetir la temporal sin llamar al servidor, y la contraseña no cambia', async () => {
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias')
    await fillChangeForm(TEMPORARY_PASSWORD, TEMPORARY_PASSWORD)

    expect(
      await screen.findByText('La contraseña nueva debe ser distinta de la temporal.')
    ).toBeInTheDocument()
    expect(app.pathname).toBe('/cambiar-contrasena')
    expect(backend.calls.some((call) => call.url.endsWith('/auth/cambiar-contrasenia'))).toBe(false)
    expect(backend.state.mustChange).toBe(true)
  })

  it('rechaza una contraseña de menos de 8 caracteres sin llamar al servidor', async () => {
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    renderApp('/materias')
    await fillChangeForm(TEMPORARY_PASSWORD, 'Corta1')

    expect(
      await screen.findByText('La contraseña nueva debe tener al menos 8 caracteres.')
    ).toBeInTheDocument()
    expect(backend.calls.some((call) => call.url.endsWith('/auth/cambiar-contrasenia'))).toBe(false)
  })

  it('rechaza una confirmación que no coincide', async () => {
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    renderApp('/materias')
    await fillChangeForm(TEMPORARY_PASSWORD, NEW_PASSWORD, 'OtraClave2026')

    expect(
      await screen.findByText('La confirmación no coincide con la contraseña nueva.')
    ).toBeInTheDocument()
    expect(backend.calls.some((call) => call.url.endsWith('/auth/cambiar-contrasenia'))).toBe(false)
  })

  it('muestra el rechazo del servidor bajo el campo y conserva la sesión', async () => {
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias')
    await fillChangeForm('Equivocada2026', NEW_PASSWORD)

    expect(await screen.findByText('La contraseña actual es incorrecta.')).toBeInTheDocument()
    expect(screen.getByLabelText(/^contraseña temporal/i)).toHaveAttribute('aria-invalid', 'true')
    expect(app.pathname).toBe('/cambiar-contrasena')
    expect(backend.state.mustChange).toBe(true)
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-inicial')
  })

  it('al cambiarla va a la entrada de su rol y el resto de las pantallas funciona', async () => {
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias')
    await fillChangeForm(TEMPORARY_PASSWORD, NEW_PASSWORD)

    await waitFor(() => expect(app.pathname).toBe('/materias'))
    expect(screen.queryByRole('heading', { name: 'Cambiar contraseña' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument()

    const change = backend.calls.find((call) => call.url.endsWith('/auth/cambiar-contrasenia'))

    expect(change?.body).toEqual({
      contrasenia_actual: TEMPORARY_PASSWORD,
      contrasenia_nueva: NEW_PASSWORD,
      contrasenia_nueva_confirmation: NEW_PASSWORD,
    })
    expect(backend.state.mustChange).toBe(false)
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-inicial')
  })

  it('el Administrador termina en Cuentas, su entrada, y no en la ruta anterior', async () => {
    stubBackend({ account: 'administrador', mustChange: true, hasSession: true })

    const app = renderApp('/ambientes')
    await fillChangeForm(TEMPORARY_PASSWORD, NEW_PASSWORD)

    await waitFor(() => expect(app.pathname).toBe('/cuentas/nueva'))
    expect(await screen.findByRole('heading', { name: 'Registrar cuenta nueva' })).toBeInTheDocument()
  })

  it('cerrar sesión sigue disponible y lleva al login', async () => {
    const user = userEvent.setup()
    const backend = stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias')
    await screen.findByRole('heading', { name: 'Cambiar contraseña' })

    await user.click(screen.getByRole('button', { name: 'Cerrar sesión' }))

    await waitFor(() => expect(app.pathname).toBe('/login'))
    expect(backend.calls.some((call) => call.url.endsWith('/auth/logout'))).toBe(true)
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(await screen.findByRole('heading', { name: 'Ingresar' })).toBeInTheDocument()
  })

  it('tras cambiarla, el login con la contraseña nueva entra directo, sin volver a pedir el cambio', async () => {
    const user = userEvent.setup()
    stubBackend({ account: 'docente', mustChange: true, hasSession: true })

    const app = renderApp('/materias')
    await fillChangeForm(TEMPORARY_PASSWORD, NEW_PASSWORD)
    await waitFor(() => expect(app.pathname).toBe('/materias'))

    await user.click(screen.getByRole('button', { name: 'Cerrar sesión' }))
    await screen.findByRole('heading', { name: 'Ingresar' })

    await logIn(SIS_BY_ACCOUNT.docente, NEW_PASSWORD)

    await waitFor(() => expect(app.pathname).toBe('/materias'))
    expect(screen.queryByRole('heading', { name: 'Cambiar contraseña' })).not.toBeInTheDocument()
  })
})
