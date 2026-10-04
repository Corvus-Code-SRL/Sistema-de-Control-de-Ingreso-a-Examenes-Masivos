import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { TOKEN_STORAGE_KEY, loginBody, loginErrors, meBody } from '@/test/authFixtures'
import { AuthProvider } from './AuthProvider'
import { useAuth } from '../hooks/useAuth'

function Probe() {
  const { estado, usuario, rol, token, iniciarSesion, cerrarSesion } = useAuth()

  return (
    <div>
      <p data-testid="estado">{estado}</p>
      <p data-testid="usuario">{usuario?.nombre_completo ?? '—'}</p>
      <p data-testid="rol">{rol?.nombre_rol ?? '—'}</p>
      <p data-testid="token">{token ?? '—'}</p>
      <button onClick={() => iniciarSesion('10452', 'password').catch(() => undefined)}>entrar</button>
      <button onClick={() => cerrarSesion()}>salir</button>
    </div>
  )
}

const estado = () => screen.getByTestId('estado').textContent

describe('AuthProvider — rehidratación', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('sin token guardado arranca anónimo y no consulta nada', () => {
    const { calls } = mockApiWith(() => undefined)

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    expect(estado()).toBe('anonimo')
    expect(calls).toHaveLength(0)
  })

  it('con token guardado queda «verificando» hasta que GET /auth/yo responde', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-guardado')
    const { calls } = mockApiWith(() => ({ body: meBody('docente') }))

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    // Antes de que responda el servidor no se sabe si la sesión sigue viva.
    expect(estado()).toBe('verificando')

    await waitFor(() => expect(estado()).toBe('autenticado'))

    expect(screen.getByTestId('usuario')).toHaveTextContent('Marcelo Quiroga Andrade')
    expect(screen.getByTestId('rol')).toHaveTextContent('Docente')
    expect(screen.getByTestId('token')).toHaveTextContent('tok-guardado')
    expect(calls[0].url).toMatch(/\/auth\/yo$/)
    expect(calls[0].headers.Authorization).toBe('Bearer tok-guardado')
  })

  it('un token vencido (401 en /yo) se descarta en silencio y la app sigue como anónima', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-muerto')
    mockApiWith(() => ({ status: 401, body: { message: 'Unauthenticated.' } }))

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    await waitFor(() => expect(estado()).toBe('anonimo'))

    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('un fallo de red al verificar no borra el token guardado', async () => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok-guardado')
    mockApiWith(() => ({ status: 500, body: {} }))

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    await waitFor(() => expect(estado()).toBe('anonimo'))

    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('tok-guardado')
  })
})

describe('AuthProvider — iniciar y cerrar sesión', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('iniciar sesión guarda el token y deja usuario y rol en el estado', async () => {
    const user = userEvent.setup()
    mockApiWith(() => ({ body: loginBody('docente', '7|abc') }))

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    await user.click(screen.getByRole('button', { name: 'entrar' }))

    await waitFor(() => expect(estado()).toBe('autenticado'))

    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('7|abc')
    expect(screen.getByTestId('rol')).toHaveTextContent('Docente')
  })

  it('un login rechazado no deja rastro de sesión', async () => {
    const user = userEvent.setup()
    mockApiWith(() => loginErrors.credentials)

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )

    await user.click(screen.getByRole('button', { name: 'entrar' }))

    await waitFor(() => expect(estado()).toBe('anonimo'))
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
  })

  it('cerrar sesión llama a /auth/logout con el token y limpia la sesión guardada', async () => {
    const user = userEvent.setup()
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
    const { calls } = mockApiWith(({ url }) =>
      url.endsWith('/auth/yo')
        ? { body: meBody('docente') }
        : { body: { data: { sesion_cerrada: true } } }
    )

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )
    await waitFor(() => expect(estado()).toBe('autenticado'))

    await user.click(screen.getByRole('button', { name: 'salir' }))

    await waitFor(() => expect(estado()).toBe('anonimo'))

    const logout = calls.find((call) => call.url.endsWith('/auth/logout'))

    expect(logout?.method).toBe('POST')
    expect(logout?.headers.Authorization).toBe('Bearer tok')
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
  })

  it('si el servidor falla al cerrar, la sesión local se cierra igual', async () => {
    const user = userEvent.setup()
    window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
    mockApiWith(({ url }) =>
      url.endsWith('/auth/yo') ? { body: meBody('docente') } : { status: 500, body: {} }
    )

    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    )
    await waitFor(() => expect(estado()).toBe('autenticado'))

    await user.click(screen.getByRole('button', { name: 'salir' }))

    await waitFor(() => expect(estado()).toBe('anonimo'))
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull()
  })
})
