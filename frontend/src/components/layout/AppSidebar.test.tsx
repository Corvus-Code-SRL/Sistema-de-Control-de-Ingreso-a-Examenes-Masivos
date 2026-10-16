import { render, screen, within } from '@testing-library/react'
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
  it('administrador: Materias es un encabezado sin enlace con Asignaciones y Catálogo debajo', () => {
    renderSidebar('administrador', '/materias/asignaciones')

    expect(screen.getByText('Materias')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Materias' })).not.toBeInTheDocument()
    expect(screen.getByText('Materias').closest('a')).toBeNull()
    expect(screen.getByText('Materias').closest('[aria-disabled]')).toBeNull()

    const group = screen.getByRole('list', { name: 'Materias' })

    expect(
      within(group)
        .getAllByRole('link')
        .map((link) => [link.textContent, link.getAttribute('href')])
    ).toEqual([
      ['Asignaciones', '/materias/asignaciones'],
      ['Catálogo', '/materias/catalogo'],
    ])
    expect(screen.queryByRole('link', { name: /asignar materia/i })).not.toBeInTheDocument()
  })

  it('administrador: se resalta solo el sub-ítem de la vista abierta', () => {
    renderSidebar('administrador', '/materias/asignaciones')

    expect(screen.getByRole('link', { name: 'Asignaciones' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Catálogo' })).not.toHaveAttribute('aria-current')
  })

  it('administrador: en el catálogo se resalta Catálogo', () => {
    renderSidebar('administrador', '/materias/catalogo')

    expect(screen.getByRole('link', { name: 'Catálogo' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Asignaciones' })).not.toHaveAttribute('aria-current')
  })

  it('administrador: Asignaciones sigue resaltada con ?periodo= y ?carrera=', () => {
    renderSidebar('administrador', '/materias/asignaciones?periodo=6&carrera=3')

    expect(screen.getByRole('link', { name: 'Asignaciones' })).toHaveAttribute('aria-current', 'page')
  })

  it('docente: Materias sigue siendo un enlace directo a /materias', () => {
    renderSidebar('docente', '/materias')

    expect(screen.getByRole('link', { name: 'Materias' })).toHaveAttribute('href', '/materias')
    expect(screen.queryByRole('link', { name: 'Asignaciones' })).not.toBeInTheDocument()
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
