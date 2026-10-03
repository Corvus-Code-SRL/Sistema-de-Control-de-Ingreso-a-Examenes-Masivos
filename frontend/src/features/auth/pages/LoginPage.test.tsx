import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import {
  TOKEN_STORAGE_KEY,
  loginBody,
  loginErrors,
  meBody,
  type AccountKey,
} from '@/test/authFixtures'
import { AuthProvider } from '../components/AuthProvider'
import { useAuth } from '../hooks/useAuth'
import { SIS_FORMAT_ERROR } from '../lib/sis'
import { LoginPage } from './LoginPage'

/**
 * Pantalla de acceso (docs/design/rnf-02/17-login.html). Cada estado se elige por el código `motivo`
 * de la respuesta, nunca por el texto del mensaje: por eso los mensajes de estas pruebas son otros.
 */

function StateProbe() {
  const { estado, rol } = useAuth()

  return <p data-testid="estado">{`${estado}:${rol?.nombre_rol ?? '-'}`}</p>
}

function renderLogin() {
  return render(
    <MemoryRouter initialEntries={['/login']}>
      <AuthProvider>
        <StateProbe />
        <LoginPage />
      </AuthProvider>
    </MemoryRouter>
  )
}

const sisField = () => screen.getByLabelText(/^Código SIS/)
const passwordField = () => screen.getByLabelText(/^Contraseña/)
const submitButton = () => screen.getByRole('button', { name: /^(Ingresar|Reintentar|Verificando)/ })

async function fillAndSubmit(codSis: string, password: string) {
  const user = userEvent.setup()

  await user.type(sisField(), codSis)
  await user.type(passwordField(), password)
  await user.click(submitButton())
}

/** Respuesta mínima con la forma de `Response` que usa `api-client`. */
function reply(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(headers),
    json: async () => body,
  } as Response
}

/** Un servidor donde `/auth/login` responde lo indicado y `/auth/yo` devuelve `me`. */
function stubServer(login: { status?: number; body: unknown; headers?: Record<string, string> }, me?: AccountKey) {
  return mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      return me ? { body: meBody(me) } : { status: 401, body: {} }
    }

    return login
  })
}

afterEach(() => {
  window.localStorage.clear()
  vi.useRealTimers()
})

describe('LoginPage — campos y accesibilidad', () => {
  it('el código SIS es un texto plano: sin inputMode, pattern ni máscara, y con los atributos del navegador', () => {
    renderLogin()

    const sis = sisField()

    expect(sis).toHaveAttribute('type', 'text')
    expect(sis).toHaveAttribute('name', 'codigo_sis')
    expect(sis).toHaveAttribute('autocomplete', 'username')
    expect(sis).toHaveAttribute('autocapitalize', 'off')
    expect(sis).toHaveAttribute('autocorrect', 'off')
    expect(sis).not.toHaveAttribute('inputmode')
    expect(sis).not.toHaveAttribute('pattern')
    expect(sis).not.toHaveAttribute('maxlength')

    expect(passwordField()).toHaveAttribute('name', 'password')
    expect(passwordField()).toHaveAttribute('autocomplete', 'current-password')
  })

  it('la ayuda muestra un ejemplo de cada formato, sin explicar la regla', () => {
    renderLogin()

    const help = document.getElementById('login-codigo-sis-help')

    expect(help).toHaveTextContent('Ejemplos: 10452 · 201800451 · ADM0001')
  })

  it('el botón está habilitado desde el inicio, aunque los campos estén vacíos', () => {
    renderLogin()

    expect(submitButton()).toBeEnabled()
  })

  it('el orden de tabulación es SIS → contraseña → ojo → Ingresar → ¿Olvidó su contraseña?', async () => {
    const user = userEvent.setup()

    renderLogin()
    await user.tab()
    expect(sisField()).toHaveFocus()
    await user.tab()
    expect(passwordField()).toHaveFocus()
    await user.tab()
    expect(screen.getByRole('button', { name: 'Mostrar contraseña' })).toHaveFocus()
    await user.tab()
    expect(submitButton()).toHaveFocus()
    await user.tab()
    expect(screen.getByRole('link', { name: '¿Olvidó su contraseña?' })).toHaveFocus()
  })

  it('el ojo es un botón con aria-pressed que muestra la contraseña sin quitar el foco del campo', async () => {
    const user = userEvent.setup()

    renderLogin()
    await user.type(passwordField(), 'Parcial.2026')

    const eye = screen.getByRole('button', { name: 'Mostrar contraseña' })

    expect(eye).toHaveAttribute('type', 'button')
    expect(eye).toHaveAttribute('aria-pressed', 'false')
    expect(passwordField()).toHaveAttribute('type', 'password')

    await user.click(eye)

    expect(screen.getByRole('button', { name: 'Ocultar contraseña' })).toHaveAttribute('aria-pressed', 'true')
    expect(passwordField()).toHaveAttribute('type', 'text')
    expect(passwordField()).toHaveValue('Parcial.2026')
    expect(passwordField()).toHaveFocus()
  })

  it('el ojo se activa con el teclado', async () => {
    const user = userEvent.setup()

    renderLogin()
    screen.getByRole('button', { name: 'Mostrar contraseña' }).focus()
    await user.keyboard('{Enter}')

    expect(passwordField()).toHaveAttribute('type', 'text')

    await user.keyboard(' ')

    expect(passwordField()).toHaveAttribute('type', 'password')
  })

  it('«¿Olvidó su contraseña?» abre la misma salida de soporte que el pie, no una ruta muerta', () => {
    renderLogin()

    const forgot = screen.getByRole('link', { name: '¿Olvidó su contraseña?' })
    const footer = screen.getByRole('link', { name: 'soporte.sciem@umss.edu' })

    expect(forgot.getAttribute('href')).toMatch(/^mailto:soporte\.sciem@umss\.edu/)
    expect(footer.getAttribute('href')).toMatch(/^mailto:soporte\.sciem@umss\.edu/)
  })
})

describe('LoginPage — validación al enviar (L.3)', () => {
  it('no valida antes de enviar: escribir o salir de un campo no muestra errores', async () => {
    const user = userEvent.setup()

    renderLogin()
    await user.type(sisField(), '2018-0045')
    await user.tab()

    expect(screen.queryByText(SIS_FORMAT_ERROR)).not.toBeInTheDocument()
  })

  it('con los dos campos vacíos no sale ninguna petición y cada error reemplaza a la ayuda', async () => {
    const user = userEvent.setup()
    const { calls } = stubServer(loginErrors.credentials)

    renderLogin()
    await user.click(submitButton())

    expect(calls).toHaveLength(0)
    expect(screen.getByText('Complete los dos campos para continuar')).toBeInTheDocument()
    expect(screen.getByText('Ingrese su código SIS.')).toBeInTheDocument()
    expect(screen.getByText('Ingrese su contraseña.')).toBeInTheDocument()
    // El error reemplaza el texto de ayuda del campo.
    expect(document.getElementById('login-codigo-sis-help')).toBeNull()
    expect(sisField()).toHaveAttribute('aria-invalid', 'true')
    expect(sisField()).toHaveAttribute('aria-describedby', 'login-codigo-sis-error')
    expect(passwordField()).toHaveAttribute('aria-invalid', 'true')
    expect(passwordField()).toHaveAttribute('aria-describedby', 'login-password-error')
    // El foco va al primer campo con error.
    expect(sisField()).toHaveFocus()
  })

  it('con solo la contraseña vacía el foco va a la contraseña', async () => {
    const user = userEvent.setup()
    const { calls } = stubServer(loginErrors.credentials)

    renderLogin()
    await user.type(sisField(), '10452')
    await user.click(submitButton())

    expect(calls).toHaveLength(0)
    expect(screen.getByText('Ingrese su contraseña.')).toBeInTheDocument()
    expect(screen.queryByText('Ingrese su código SIS.')).not.toBeInTheDocument()
    expect(screen.queryByText('Complete los dos campos para continuar')).not.toBeInTheDocument()
    expect(passwordField()).toHaveFocus()
  })

  it('un código con formato no reconocido dice los tres formatos y no sale ninguna petición', async () => {
    const user = userEvent.setup()
    const { calls } = stubServer(loginErrors.credentials)

    renderLogin()
    await user.type(sisField(), '2018-0045')
    await user.type(passwordField(), 'password')
    await user.click(submitButton())

    expect(calls).toHaveLength(0)
    expect(screen.getByText(SIS_FORMAT_ERROR)).toBeInTheDocument()
    expect(SIS_FORMAT_ERROR).toBe(
      'El código no tiene un formato válido. Use 5 o 9 dígitos, o un código como ADM0001.'
    )
    expect(sisField()).toHaveFocus()
  })

  it.each(['10452', '201800451', 'ADM0001', 'adm0001'])('acepta %s como formato reconocido', async (sis) => {
    const user = userEvent.setup()
    const { calls } = stubServer(loginErrors.credentials)

    renderLogin()
    await user.type(sisField(), sis)
    await user.type(passwordField(), 'password')
    await user.click(submitButton())

    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
    expect(screen.queryByText(SIS_FORMAT_ERROR)).not.toBeInTheDocument()
  })

  it('el error de un campo se borra al escribir en él, no al salir', async () => {
    const user = userEvent.setup()

    renderLogin()
    await user.click(submitButton())

    expect(screen.getByText('Ingrese su código SIS.')).toBeInTheDocument()

    await user.type(sisField(), '1')

    expect(screen.queryByText('Ingrese su código SIS.')).not.toBeInTheDocument()
    expect(screen.getByText('Ingrese su contraseña.')).toBeInTheDocument()
  })

  it('solo se recortan los espacios de los extremos del código al enviarlo', async () => {
    const user = userEvent.setup()
    const { calls } = stubServer(loginErrors.credentials)

    renderLogin()
    await user.type(sisField(), '  201800451  ')
    await user.type(passwordField(), 'password')
    await user.click(submitButton())

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(calls[0].body).toEqual({ cod_sis: '201800451', password: 'password' })
  })
})

describe('LoginPage — los resultados, elegidos por `motivo` y no por el mensaje', () => {
  it('401 (credenciales_invalidas): una sola alerta roja para los dos campos', async () => {
    stubServer({ status: 401, body: { message: 'texto cualquiera', motivo: 'credenciales_invalidas' } })

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveAttribute('data-failure-kind', 'credenciales')
    expect(alert).toHaveTextContent('Código SIS o contraseña incorrectos')
    expect(alert).toHaveTextContent(
      'Revise los datos e intente de nuevo. Después de 5 intentos deberá esperar un minuto.'
    )
    // No dice cuál de los dos campos falló: ninguno se marca como inválido.
    expect(sisField()).not.toHaveAttribute('aria-invalid')
    expect(passwordField()).not.toHaveAttribute('aria-invalid')
    expect(screen.getByTestId('estado')).toHaveTextContent('anonimo')
  })

  it('el mismo 401 con otro texto de mensaje se ve igual: manda el código', async () => {
    stubServer({ status: 401, body: { message: 'Cuenta inactiva', motivo: 'credenciales_invalidas' } })

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    expect(await screen.findByText('Código SIS o contraseña incorrectos')).toBeInTheDocument()
    expect(screen.queryByText('Su cuenta está inactiva')).not.toBeInTheDocument()
  })

  it('403 (cuenta_inactiva): el formulario se reemplaza por el panel gris, sin campos', async () => {
    // El mensaje habla de rol a propósito: el panel lo decide el código.
    stubServer({ status: 403, body: { message: 'Su cuenta no tiene un rol vigente.', motivo: 'cuenta_inactiva' } })

    renderLogin()
    await fillAndSubmit('201800451', 'password')

    expect(await screen.findByRole('heading', { name: 'No puede ingresar' })).toBeInTheDocument()
    expect(screen.getByText('Su cuenta está inactiva')).toBeInTheDocument()
    expect(screen.getByText(/fue dada de baja, así que no puede usar SCIEM\./)).toBeInTheDocument()
    expect(
      screen.getByText(
        'Esto no se resuelve desde aquí: el Administrador de su facultad es quien puede reactivarla.'
      )
    ).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Código SIS/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ingresar' })).not.toBeInTheDocument()
    expect(screen.queryByText('Falta un paso')).not.toBeInTheDocument()

    const mail = screen.getByRole('link', { name: 'Escribir al Administrador' })

    expect(mail.getAttribute('href')).toContain('mailto:soporte.sciem@umss.edu?subject=')
    expect(decodeURIComponent(mail.getAttribute('href') ?? '')).toContain('201800451')
  })

  it('«Usar otra cuenta» devuelve el formulario vacío con el foco en el código', async () => {
    const user = userEvent.setup()

    stubServer({ status: 403, body: { message: 'x', motivo: 'cuenta_inactiva' } })

    renderLogin()
    await fillAndSubmit('201800451', 'password')
    await user.click(await screen.findByRole('button', { name: 'Usar otra cuenta' }))

    expect(sisField()).toHaveValue('')
    expect(passwordField()).toHaveValue('')
    expect(sisField()).toHaveFocus()
  })

  it('403 (sin_rol_vigente): panel azul con la acción primaria; dice que las credenciales eran correctas', async () => {
    stubServer({ status: 403, body: { message: 'Su cuenta está deshabilitada.', motivo: 'sin_rol_vigente' } })

    renderLogin()
    await fillAndSubmit('202000315', 'password')

    expect(await screen.findByRole('heading', { name: 'Falta un paso' })).toBeInTheDocument()
    expect(screen.getByText('Su cuenta todavía no tiene un rol')).toBeInTheDocument()
    expect(
      screen.getByText(/existe y la contraseña es correcta, pero el Administrador aún no le asignó un rol\./)
    ).toBeInTheDocument()
    expect(screen.getByText('Si ya lo solicitó, vuelva a intentar en unos minutos.')).toBeInTheDocument()
    expect(screen.queryByText('Su cuenta está inactiva')).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/^Código SIS/)).not.toBeInTheDocument()

    const request = screen.getByRole('link', { name: 'Solicitar la asignación de rol' })
    const href = decodeURIComponent(request.getAttribute('href') ?? '')

    expect(href).toContain('202000315')
    expect(href).toContain('Fecha de la solicitud')
    // Es el único de los cuatro con una acción concreta: el botón es el primario.
    expect(request).toHaveAttribute('data-variant', 'default')
    expect(screen.queryByRole('link', { name: 'Escribir al Administrador' })).not.toBeInTheDocument()
  })

  it('«Volver» deja el formulario en reposo', async () => {
    const user = userEvent.setup()

    stubServer({ status: 403, body: { message: 'x', motivo: 'sin_rol_vigente' } })

    renderLogin()
    await fillAndSubmit('202000315', 'password')
    await user.click(await screen.findByRole('button', { name: 'Volver' }))

    expect(sisField()).toHaveValue('')
    expect(submitButton()).toBeEnabled()
  })

  it('429: aviso ámbar y espera, distinto de credenciales incorrectas', async () => {
    stubServer({ status: 429, body: { message: 'Too Many Attempts.' }, headers: { 'Retry-After': '45' } })

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    const notice = await screen.findByText('Demasiados intentos')

    expect(notice.closest('[data-failure-kind]')).toHaveAttribute('data-failure-kind', 'limitado')
    expect(screen.getByText(/un minuto/)).toBeInTheDocument()
    expect(screen.getByText('0:45')).toBeInTheDocument()
    expect(screen.queryByText('Código SIS o contraseña incorrectos')).not.toBeInTheDocument()
  })

  it('red caída o 5xx: aviso neutro que conserva lo escrito, contraseña incluida, y ofrece «Reintentar»', async () => {
    mockApiWith(() => undefined)

    renderLogin()
    await fillAndSubmit('10452', 'password')

    const notice = await screen.findByText('No se pudo conectar con el servidor')

    expect(notice.closest('[data-failure-kind]')).toHaveAttribute('data-failure-kind', 'indisponible')
    expect(screen.getByText('Revise su conexión e intente de nuevo. Sus datos no se enviaron.')).toBeInTheDocument()
    expect(sisField()).toHaveValue('10452')
    expect(passwordField()).toHaveValue('password')
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeEnabled()
    // No dice nada de la cuenta.
    expect(screen.queryByText(/incorrectos|inactiva|rol vigente|asignó un rol/i)).not.toBeInTheDocument()
  })

  it('un 5xx se ve como la red caída', async () => {
    stubServer({ status: 503, body: { message: 'Service Unavailable' } })

    renderLogin()
    await fillAndSubmit('10452', 'password')

    expect(await screen.findByText('No se pudo conectar con el servidor')).toBeInTheDocument()
    expect(passwordField()).toHaveValue('password')
  })

  it('«Reintentar» reenvía lo mismo y entra cuando ya hay servicio', async () => {
    const user = userEvent.setup()
    let attempts = 0

    const { calls } = mockApiWith(({ url }) => {
      if (url.endsWith('/auth/yo')) return { body: meBody('docente') }

      attempts += 1

      return attempts === 1 ? { status: 503, body: {} } : { body: loginBody('docente') }
    })

    renderLogin()
    await fillAndSubmit('10452', 'password')
    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente'))

    const logins = calls.filter((call) => call.url.endsWith('/auth/login'))

    expect(logins).toHaveLength(2)
    expect(logins[1].body).toEqual({ cod_sis: '10452', password: 'password' })
  })

  it('15 s sin respuesta se ve como la red caída, no como credenciales incorrectas', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (_url: unknown, init: RequestInit) =>
          new Promise((_resolve, reject) => {
            init.signal?.addEventListener('abort', () =>
              reject(new DOMException('The operation was aborted.', 'AbortError'))
            )
          })
      )
    )

    renderLogin()
    await fillAndSubmit('10452', 'password')
    await act(() => vi.advanceTimersByTimeAsync(15_000))

    expect(await screen.findByText('No se pudo conectar con el servidor')).toBeInTheDocument()
    expect(passwordField()).toHaveValue('password')
  })
})

describe('LoginPage — 401: el código sobrevive, la contraseña se vacía y recibe el foco', () => {
  it('conserva el código tal como se escribió, vacía la contraseña y la enfoca', async () => {
    stubServer(loginErrors.credentials)

    renderLogin()
    await fillAndSubmit('201800451', 'mala')

    await screen.findByText('Código SIS o contraseña incorrectos')

    expect(sisField()).toHaveValue('201800451')
    expect(passwordField()).toHaveValue('')
    expect(passwordField()).toHaveFocus()
  })

  it('un 401 del login no cierra una sesión que ya estuviera abierta', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-vigente')
    stubServer(loginErrors.credentials, 'docente')

    renderLogin()
    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente'))

    await fillAndSubmit('10452', 'mala')
    await screen.findByText('Código SIS o contraseña incorrectos')

    expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-vigente')
  })
})

describe('LoginPage — envío en curso (L.4)', () => {
  it('muestra «Verificando…», deja los campos en solo lectura, oculta la salida y marca aria-busy', async () => {
    let release!: () => void
    const pending = new Promise<void>((resolve) => (release = resolve))

    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        await pending

        return reply(401, loginErrors.credentials.body)
      })
    )

    renderLogin()
    await fillAndSubmit('10452', 'password')

    const button = screen.getByRole('button', { name: /Verificando/ })

    expect(button).toBeDisabled()
    expect(sisField()).toHaveAttribute('readonly')
    expect(passwordField()).toHaveAttribute('readonly')
    expect(screen.queryByRole('link', { name: '¿Olvidó su contraseña?' })).not.toBeInTheDocument()
    expect(screen.getByText('No cierre la aplicación')).toBeInTheDocument()
    expect(document.querySelector('form')).toHaveAttribute('aria-busy', 'true')

    release()
    await screen.findByText('Código SIS o contraseña incorrectos')

    expect(document.querySelector('form')).toHaveAttribute('aria-busy', 'false')
  })

  it('un segundo envío mientras hay uno en curso no dispara otra petición', async () => {
    let release!: () => void
    const pending = new Promise<void>((resolve) => (release = resolve))
    const fetchMock = vi.fn(async () => {
      await pending

      return reply(401, loginErrors.credentials.body)
    })

    vi.stubGlobal('fetch', fetchMock)

    renderLogin()
    await fillAndSubmit('10452', 'password')

    const form = document.querySelector('form') as HTMLFormElement

    fireEvent.submit(form)
    fireEvent.submit(form)

    expect(fetchMock).toHaveBeenCalledTimes(1)

    release()
    await screen.findByText('Código SIS o contraseña incorrectos')
  })
})

describe('LoginPage — espera por demasiados intentos (L.8)', () => {
  async function submitThrottled(headers: Record<string, string>) {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    stubServer({ status: 429, body: { message: 'Too Many Attempts.' }, headers })

    renderLogin()

    fireEvent.change(sisField(), { target: { value: '10452' } })
    fireEvent.change(passwordField(), { target: { value: 'mala' } })
    fireEvent.submit(document.querySelector('form') as HTMLFormElement)

    await screen.findByText('Demasiados intentos')
  }

  it('deshabilita el botón y los campos durante la cuenta regresiva y los reactiva solos al llegar a cero', async () => {
    await submitThrottled({ 'Retry-After': '3' })

    expect(screen.getByRole('button', { name: 'Ingresar' })).toBeDisabled()
    expect(sisField()).toHaveAttribute('readonly')
    expect(passwordField()).toHaveAttribute('readonly')
    expect(screen.getByText('0:03')).toBeInTheDocument()

    await act(() => vi.advanceTimersByTimeAsync(2_000))

    expect(screen.getByRole('button', { name: 'Ingresar' })).toBeDisabled()
    expect(screen.getByText('0:01')).toBeInTheDocument()

    await act(() => vi.advanceTimersByTimeAsync(1_000))

    expect(screen.getByRole('button', { name: 'Ingresar' })).toBeEnabled()
    expect(sisField()).not.toHaveAttribute('readonly')
    expect(passwordField()).not.toHaveAttribute('readonly')
    expect(screen.queryByText('Demasiados intentos')).not.toBeInTheDocument()
    // El código sigue ahí; la contraseña se vació.
    expect(sisField()).toHaveValue('10452')
    expect(passwordField()).toHaveValue('')
  })

  it('sin Retry-After asume 60 segundos', async () => {
    await submitThrottled({})

    expect(screen.getByText('1:00')).toBeInTheDocument()

    await act(() => vi.advanceTimersByTimeAsync(59_000))

    expect(screen.getByRole('button', { name: 'Ingresar' })).toBeDisabled()

    await act(() => vi.advanceTimersByTimeAsync(1_000))

    expect(screen.getByRole('button', { name: 'Ingresar' })).toBeEnabled()
  })

  it('un envío durante la espera no sale', async () => {
    await submitThrottled({ 'Retry-After': '30' })

    const calls = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls.length

    fireEvent.submit(document.querySelector('form') as HTMLFormElement)

    expect((fetch as unknown as ReturnType<typeof vi.fn>).mock.calls.length).toBe(calls)
  })

  it('anuncia la cuenta regresiva con aria-live="polite" como máximo cada 15 segundos', async () => {
    await submitThrottled({})

    const announcement = () =>
      Array.from(document.querySelectorAll('[aria-live="polite"]'))
        .map((node) => node.textContent ?? '')
        .find((text) => text.startsWith('Podrá reintentar en'))

    expect(announcement()).toBe('Podrá reintentar en 1:00')

    await act(() => vi.advanceTimersByTimeAsync(7_000))
    expect(announcement()).toBe('Podrá reintentar en 1:00')

    await act(() => vi.advanceTimersByTimeAsync(8_000))
    expect(announcement()).toBe('Podrá reintentar en 0:45')
  })
})

describe('LoginPage — ingreso correcto (L.10)', () => {
  /** `/auth/yo` queda en espera hasta que la prueba lo libere. */
  function stubSlowYo(me: AccountKey, hook?: (headers: Record<string, string>) => void) {
    let release!: () => void
    const pending = new Promise<void>((resolve) => (release = resolve))

    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
        if (String(input).endsWith('/auth/yo')) {
          hook?.((init.headers ?? {}) as Record<string, string>)

          await pending

          return reply(200, meBody(me))
        }

        return reply(200, loginBody('docente', '9|abc'))
      })
    )

    return release
  }

  it('mientras /yo responde muestra el panel de éxito y no el formulario, con el token ya guardado', async () => {
    let storedWhenYoWasAsked: string | null = null
    let authorization: string | undefined

    const release = stubSlowYo('docente', (headers) => {
      storedWhenYoWasAsked = window.localStorage.getItem(TOKEN_STORAGE_KEY)
      authorization = headers.Authorization
    })

    renderLogin()
    await fillAndSubmit('10452', 'password')

    expect(await screen.findByRole('heading', { name: 'Bienvenido' })).toBeInTheDocument()
    expect(screen.getByText('Ingreso correcto')).toBeInTheDocument()
    expect(screen.getByText('Abriendo su inicio…')).toBeInTheDocument()
    expect(screen.getByText('Cargando su rol y su navegación')).toBeInTheDocument()
    // Sin el formulario no hay un botón «Ingresar» que volver a pulsar.
    expect(screen.queryByRole('button', { name: 'Ingresar' })).not.toBeInTheDocument()

    // El token se guardó antes de pedir /yo, y /yo lo llevó.
    expect(storedWhenYoWasAsked).toBe('9|abc')
    expect(authorization).toBe('Bearer 9|abc')
    expect(screen.getByTestId('estado')).toHaveTextContent('anonimo')

    release()

    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente'))
  })

  it('si /yo falla vuelve al formulario con el aviso de red, sin dejar token ni sesión', async () => {
    mockApiWith(({ url }) =>
      url.endsWith('/auth/yo') ? { status: 503, body: {} } : { body: loginBody('docente', '9|abc') }
    )

    renderLogin()
    await fillAndSubmit('10452', 'password')

    expect(await screen.findByText('No se pudo conectar con el servidor')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Bienvenido' })).not.toBeInTheDocument()
    expect(screen.getByTestId('estado')).toHaveTextContent('anonimo')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(sisField()).toHaveValue('10452')
    expect(passwordField()).toHaveValue('password')
  })

  it('el rol sale de /yo y nunca del formato del código: un código de 5 dígitos puede ser Administrador', async () => {
    // Un formato de docente (5 dígitos) que /yo declara Administrador.
    mockApiWith(({ url }) =>
      url.endsWith('/auth/yo') ? { body: meBody('administrador') } : { body: loginBody('docente') }
    )

    renderLogin()
    await fillAndSubmit('10452', 'password')

    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Administrador'))
  })

  it('y un código alfanumérico puede ser Docente', async () => {
    mockApiWith(({ url }) =>
      url.endsWith('/auth/yo') ? { body: meBody('docente') } : { body: loginBody('administrador') }
    )

    renderLogin()
    await fillAndSubmit('ADM0001', 'password')

    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente'))
  })
})
