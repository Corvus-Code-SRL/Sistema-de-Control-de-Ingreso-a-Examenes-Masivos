import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
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

  it('límite de intentos (429): estado propio, pero con el mismo mensaje que credenciales incorrectas', async () => {
    mockApiWith(() => loginErrors.throttled)

    renderLogin()
    await fillAndSubmit('10452', 'mala')

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveTextContent('Código SIS o contraseña incorrectos.')
    expect(alert).toHaveAttribute('data-failure-kind', 'limitado')
    expect(screen.getByLabelText('Contraseña')).toHaveFocus()
  })

  it('una caída de red se distingue de unas credenciales incorrectas', async () => {
    const user = userEvent.setup()
    mockApiWith(() => undefined)

    renderLogin()
    await user.type(screen.getByLabelText('Código SIS'), '10452')
    await user.type(screen.getByLabelText('Contraseña'), 'password')
    await user.click(screen.getByRole('button', { name: 'Ingresar' }))

    const alert = await screen.findByRole('alert')

    expect(alert).toHaveAttribute('data-failure-kind', 'red')
  })
})
