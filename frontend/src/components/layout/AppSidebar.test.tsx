import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import type { Area } from '@/features/auth'
import { AppSidebar } from './AppSidebar'

const session = vi.hoisted(() => ({ area: 'docente' as Area }))

vi.mock('@/features/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/auth')>()),
  useCurrentUser: () => ({
    user: { nombre: 'Prueba Usuario', iniciales: 'PU', area: session.area },
    area: session.area,
  }),
  useAuth: () => ({ estado: 'autenticado', cerrarSesion: vi.fn() }),
}))

function renderSidebar(area: Area, route: string) {
  session.area = area

  return render(
    <MemoryRouter initialEntries={[route]}>
      <AppSidebar />
    </MemoryRouter>
  )
}

describe('AppSidebar', () => {
  it('administrador: muestra una sola entrada Materias y ninguna de Asignar materia', () => {
    renderSidebar('administrador', '/materias')

    expect(screen.getAllByRole('link', { name: 'Materias' })).toHaveLength(1)
    expect(screen.getByRole('link', { name: 'Materias' })).toHaveAttribute('href', '/materias')
    expect(screen.queryByRole('link', { name: /asignar materia/i })).not.toBeInTheDocument()
    expect(screen.queryByText(/asignar materia/i)).not.toBeInTheDocument()
  })

  it('administrador: Materias se resalta en el catálogo y en la pestaña de asignaciones', () => {
    renderSidebar('administrador', '/materias')
    expect(screen.getByRole('link', { name: 'Materias' })).toHaveAttribute('aria-current', 'page')
  })

  it('administrador: Materias sigue resaltada con ?tab=asignaciones', () => {
    renderSidebar('administrador', '/materias?tab=asignaciones')
    expect(screen.getByRole('link', { name: 'Materias' })).toHaveAttribute('aria-current', 'page')
  })

  it('docente: Incidencias queda deshabilitada con su tooltip y sin número', () => {
    renderSidebar('docente', '/materias')

    const incidencias = screen.getByText('Incidencias').closest('span[aria-disabled]')

    expect(incidencias).toHaveAttribute('aria-disabled', 'true')
    expect(incidencias).toHaveAttribute('title', 'Disponible en una próxima entrega')
    expect(incidencias).toHaveTextContent(/^Incidencias$/)
  })

  it('docente: los ítems habilitados son enlaces y los pendientes no', () => {
    renderSidebar('docente', '/materias')

    expect(screen.getByRole('link', { name: 'Mis cursos' })).toHaveAttribute('href', '/mis-cursos')
    expect(screen.queryByRole('link', { name: 'Incidencias' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Historial' })).not.toBeInTheDocument()
  })
})
