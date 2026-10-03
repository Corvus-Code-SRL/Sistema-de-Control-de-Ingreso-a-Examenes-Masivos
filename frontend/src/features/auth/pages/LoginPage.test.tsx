import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { TOKEN_STORAGE_KEY, loginBody, loginErrors, meBody, type AccountKey } from '@/test/authFixtures'
import { AuthProvider } from '../components/AuthProvider'
import { useAuth } from '../hooks/useAuth'
import { LoginPage } from './LoginPage'

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

async function fillAndSubmit(codSis: string, password: string) {
  const user = userEvent.setup()

  await user.type(screen.getByLabelText('Código SIS'), codSis)
  await user.type(screen.getByLabelText('Contraseña'), password)
  await user.click(screen.getByRole('button', { name: 'Ingresar' }))
}

describe('LoginPage — resultados del inicio de sesión', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it.each<[string, AccountKey, string]>([
    ['ADM0001', 'administrador', 'Administrador'],
    ['10452', 'docente', 'Docente'],
    ['201800451', 'auxiliar', 'Auxiliar'],
  ])('%s entra y la sesión queda con el rol %s', async (codSis, account, role) => {
    const { calls } = mockApiWith(() => ({ body: loginBody(account) }))

    renderLogin()
    await fillAndSubmit(codSis, 'password')

    await waitFor(() =>
      expect(screen.getByTestId('estado')).toHaveTextContent(`autenticado:${role}`)
    )

    // El código viaja tal como se escribió: el backend lo normaliza.
    expect(calls[0].body).toEqual({ cod_sis: codSis, password: 'password' })
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe(`1|token-${account}`)
  })

  it('credenciales incorrectas (401): error bajo la contraseña, sin tocar la sesión', async () => {
    mockApiWith(() => loginErrors.credentials)

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    const error = await screen.findByRole('alert')

    expect(error).toHaveTextContent('Código SIS o contraseña incorrectos.')
    expect(error).toHaveAttribute('data-failure-kind', 'credenciales')
    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('aria-describedby', error.id)
    expect(screen.getByTestId('estado')).toHaveTextContent('anonimo')
  })

  it('tras un fallo conserva el código SIS tal como se escribió, vacía la contraseña y la enfoca', async () => {
    mockApiWith(() => loginErrors.credentials)

    renderLogin()
    await fillAndSubmit('201800451', 'mala')

    await screen.findByRole('alert')

    expect(screen.getByLabelText('Código SIS')).toHaveValue('201800451')
    expect(screen.getByLabelText('Contraseña')).toHaveValue('')
    expect(screen.getByLabelText('Contraseña')).toHaveFocus()
  })

  it('un 401 del login no cierra una sesión que ya estuviera abierta', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-vigente')
    mockApiWith(({ url }) =>
      url.endsWith('/auth/yo') ? { body: meBody('docente') } : loginErrors.credentials
    )

    renderLogin()
    await waitFor(() => expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente'))

    await fillAndSubmit('10452', 'mala')
    await screen.findByRole('alert')

    // Siguen la sesión y el token: no era una sesión muerta, eran credenciales incorrectas.
    expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-vigente')
  })

  it('cuenta inactiva (403): mensaje propio del motivo', async () => {
    mockApiWith(() => loginErrors.inactive)

    renderLogin()
    await fillAndSubmit('10398', 'password')

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveTextContent('Su cuenta está deshabilitada. Contacte al Administrador.')
    expect(alert).toHaveAttribute('data-failure-kind', 'cuenta-inactiva')
    expect(screen.getByLabelText('Contraseña')).toHaveValue('')
  })

  it('sin rol vigente (403): mensaje propio del motivo', async () => {
    mockApiWith(() => loginErrors.noRole)

    renderLogin()
    await fillAndSubmit('202000315', 'password')

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveTextContent('Su cuenta no tiene un rol vigente. Contacte al Administrador.')
    expect(alert).toHaveAttribute('data-failure-kind', 'sin-rol')
  })

  it('límite de intentos (429): estado propio, no se confunde con credenciales incorrectas', async () => {
    mockApiWith(() => ({ ...loginErrors.throttled, headers: { 'Retry-After': '45' } }))

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveAttribute('data-failure-kind', 'limitado')
    expect(alert).not.toHaveTextContent('Código SIS o contraseña incorrectos.')
    expect(screen.getByLabelText('Contraseña')).toHaveFocus()
  })

  describe('petición no completada (sin conexión, tiempo agotado o 5xx)', () => {
    afterEach(() => {
      vi.useRealTimers()
    })

    it.each([
      ['una caída de red', () => mockApiWith(() => undefined)],
      ['un error del servidor (503)', () => mockApiWith(() => ({ status: 503, body: { message: 'Service Unavailable' } }))],
    ])('%s: aviso neutro que conserva lo escrito, contraseña incluida', async (_label, stub) => {
      stub()

      renderLogin()
      await fillAndSubmit('10452', 'password')

      const alert = await screen.findByRole('alert')

      expect(alert).toHaveAttribute('data-failure-kind', 'indisponible')
      // No dice nada de la cuenta: nada que ver con credenciales.
      expect(alert).not.toHaveTextContent('incorrectos')
      expect(screen.getByLabelText('Código SIS')).toHaveValue('10452')
      expect(screen.getByLabelText('Contraseña')).toHaveValue('password')
      expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
    })

    it('15 s sin respuesta es indisponible, no un problema de credenciales', async () => {
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
      await vi.advanceTimersByTimeAsync(15_000)

      const alert = await screen.findByRole('alert')

      expect(alert).toHaveAttribute('data-failure-kind', 'indisponible')
      expect(screen.getByLabelText('Contraseña')).toHaveValue('password')
    })

    it('«Reintentar» reenvía lo mismo que se había escrito y entra si ya hay servicio', async () => {
      const user = userEvent.setup()
      let attempts = 0

      const { calls } = mockApiWith(() => {
        attempts += 1

        return attempts === 1 ? { status: 503, body: {} } : { body: loginBody('docente') }
      })

      renderLogin()
      await fillAndSubmit('10452', 'password')

      await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

      await waitFor(() =>
        expect(screen.getByTestId('estado')).toHaveTextContent('autenticado:Docente')
      )
      expect(calls).toHaveLength(2)
      expect(calls[1].body).toEqual({ cod_sis: '10452', password: 'password' })
    })
  })
})
