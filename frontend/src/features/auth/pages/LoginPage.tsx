import { useLoginForm } from '../hooks/useLoginForm'

/**
 * FORMULARIO PROVISIONAL — a propósito sin diseño.
 *
 * Dos campos y un botón, suficientes para recorrer el flujo completo de punta a punta. La fase 2b
 * reemplaza SOLO este marcado por la pantalla real: toda la lógica vive en `useLoginForm` y en
 * `authService`, y no debe moverse aquí. Los atributos `data-failure-kind` existen para que 2b
 * estile cada resultado: credenciales (también 429), cuenta inactiva, sin rol, red y desconocido.
 */
export function LoginPage() {
  const { codSis, setCodSis, password, setPassword, isSubmitting, failure, passwordRef, submit } =
    useLoginForm()

  const credentialsFailed = failure?.kind === 'credenciales' || failure?.kind === 'limitado'

  return (
    <main>
      <h1>Iniciar sesión</h1>

      {failure && !credentialsFailed && (
        <p role="alert" data-failure-kind={failure.kind}>
          {failure.message}
        </p>
      )}

      <form onSubmit={submit} noValidate>
        <div>
          <label htmlFor="login-cod-sis">Código SIS</label>
          <input
            id="login-cod-sis"
            name="cod_sis"
            type="text"
            autoComplete="username"
            value={codSis}
            onChange={(event) => setCodSis(event.target.value)}
          />
        </div>

        <div>
          <label htmlFor="login-password">Contraseña</label>
          <input
            id="login-password"
            name="password"
            type="password"
            autoComplete="current-password"
            ref={passwordRef}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            aria-invalid={credentialsFailed || undefined}
            aria-describedby={credentialsFailed ? 'login-error' : undefined}
          />
          {failure && credentialsFailed && (
            <p id="login-error" role="alert" data-failure-kind={failure.kind}>
              {failure.message}
            </p>
          )}
        </div>

        <button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Ingresando…' : 'Ingresar'}
        </button>
      </form>
    </main>
  )
}
