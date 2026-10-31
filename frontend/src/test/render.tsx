import type { ReactElement } from 'react'
import { render, type RenderResult } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

/**
 * Renderiza dentro de un router en memoria.
 *
 * Las vistas navegan con enlaces y leen parámetros de la URL, así que sin router
 * ni siquiera se montan.
 */
export function renderWithRouter(
  ui: ReactElement,
  { route = '/', path }: { route?: string; path?: string } = {}
): RenderResult {
  if (!path) {
    return render(<MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>)
  }

  return render(
    <MemoryRouter initialEntries={[route]}>
      <Routes>
        <Route path={path} element={ui} />
      </Routes>
    </MemoryRouter>
  )
}
