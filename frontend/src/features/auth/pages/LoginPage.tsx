import { LoginForm } from '../components/LoginForm'
import { LoginLayout } from '../components/LoginLayout'
import {
  InactiveAccountPanel,
  LoginSuccessPanel,
  RoleMissingPanel,
} from '../components/LoginStatePanels'
import { useLoginForm } from '../hooks/useLoginForm'
import { useVirtualKeyboard } from '../hooks/useVirtualKeyboard'

/**
 * Pantalla de acceso (docs/design/rnf-02/17-login.html).
 *
 * Solo pinta. Toda la lógica vive en `useLoginForm` y en `authService`; qué panel se muestra lo decide
 * el código `motivo` de la respuesta del servidor, nunca el texto del mensaje, y el rol nunca se deduce
 * del formato del código SIS: sale de GET /api/auth/yo.
 */
export function LoginPage() {
  const form = useLoginForm()
  const keyboardOpen = useVirtualKeyboard()

  return (
    <LoginLayout>
      {form.view === 'form' && <LoginForm form={form} keyboardOpen={keyboardOpen} />}

      {form.view === 'inactive' && <InactiveAccountPanel sis={form.attemptedSis} onReset={form.reset} />}

      {form.view === 'no-role' && <RoleMissingPanel sis={form.attemptedSis} onReset={form.reset} />}

      {form.view === 'success' && <LoginSuccessPanel />}
    </LoginLayout>
  )
}
