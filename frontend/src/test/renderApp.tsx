import { render, type RenderResult } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { AppRouter } from '@/app/router/AppRouter'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AuthProvider } from '@/features/auth'

function LocationSpy({ onChange }: { onChange: (pathname: string) => void }) {
  onChange(useLocation().pathname)
  return null
}

export interface RenderedApp extends RenderResult {
  readonly pathname: string
}

/**
 * Monta la aplicación como en producción —sesión, router y sus rutas— sobre un router en memoria.
 *
 * `extra` se dibuja junto a las rutas, dentro de la sesión: sirve para disparar peticiones propias
 * de la prueba, como la que descubre que el token murió.
 */
export function renderApp(route: string, extra?: ReactNode): RenderedApp {
  let pathname = ''

  const utils = render(
    <MemoryRouter initialEntries={[route]}>
      <AuthProvider>
        <TooltipProvider>
          <AppRouter />
          {extra}
          <LocationSpy onChange={(value) => (pathname = value)} />
        </TooltipProvider>
      </AuthProvider>
    </MemoryRouter>
  )

  // Un getter vivo: `Object.assign` lo ejecutaría una vez y congelaría la ruta inicial.
  Object.defineProperty(utils, 'pathname', { get: () => pathname })

  return utils as RenderedApp
}
