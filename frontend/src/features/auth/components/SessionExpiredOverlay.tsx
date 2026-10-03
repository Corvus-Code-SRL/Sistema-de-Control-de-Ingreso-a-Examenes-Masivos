import { LogIn } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useAuth } from '../hooks/useAuth'
import { LOGIN_REDIRECT_KEY } from '../hooks/useLoginForm'

export const LOGIN_PATH = '/login'

/**
 * Bloquea la pantalla cuando la sesión murió (una petición con token recibió 401).
 *
 * No redirige en silencio —el usuario no sabría qué pasó— ni deja la pantalla usable: seguir
 * pulsando botones contra una sesión muerta, con datos viejos a la vista, es justo lo que no
 * puede ocurrir a la puerta de un examen. El botón lleva al login recordando la ruta actual, y
 * tras volver a entrar se regresa a ella.
 */
export function SessionExpiredOverlay() {
  const { estado } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  // En el propio login no se bloquea: es donde se resuelve.
  if (estado !== 'expirada' || location.pathname === LOGIN_PATH) return null

  const goToLogin = () =>
    navigate(LOGIN_PATH, {
      state: { [LOGIN_REDIRECT_KEY]: `${location.pathname}${location.search}` },
    })

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-background/90 p-4 backdrop-blur-sm">
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="session-expired-title"
        aria-describedby="session-expired-description"
        className="w-full max-w-sm space-y-4 rounded-xl border bg-card p-6 text-center shadow-lg"
      >
        <h2 id="session-expired-title" className="sciem-h3">
          Su sesión expiró
        </h2>
        <p id="session-expired-description" className="text-sm text-muted-foreground">
          Inicie sesión de nuevo para continuar. Volverá a esta misma pantalla.
        </p>
        <Button autoFocus onClick={goToLogin}>
          <LogIn className="size-4" aria-hidden="true" />
          Iniciar sesión
        </Button>
      </div>
    </div>
  )
}
