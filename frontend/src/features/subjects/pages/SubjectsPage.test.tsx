import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { SubjectsPage } from './SubjectsPage'
import { informatica, makeSubject, subjectCatalogResponse } from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

describe('SubjectsPage', () => {
  it('lista el catálogo completo, propio y ajeno', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([
        makeSubject({ id_materia: 1, nombre: 'Bases de Datos I' }),
        makeSubject({
          id_materia: 2,
          nombre: 'Algebra Lineal',
          es_mia: false,
          cantidad_grupos: 0,
        }),
      ]),
    })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    expect(screen.getAllByText('Bases de Datos I').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Algebra Lineal').length).toBeGreaterThan(0)
  })

  it('muestra la carrera de cada entrada para distinguir la misma materia', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([
        makeSubject({ id_carrera: 1 }),
        makeSubject({ id_carrera: 2, carrera: informatica }),
      ]),
    })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    expect(screen.getAllByText('Ingenieria de Sistemas').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Ingenieria Informatica').length).toBeGreaterThan(0)
  })

  it('solo ofrece «Ver grupos» sobre las materias propias y activas', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([
        makeSubject({ id_materia: 1 }),
        makeSubject({ id_materia: 2, es_mia: false, cantidad_grupos: 0 }),
        makeSubject({ id_materia: 3, activa: false }),
      ]),
    })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    // Una entrada por variante de escritorio y móvil, ambas del mismo par propio.
    expect(screen.getAllByRole('link', { name: /ver grupos/i })).toHaveLength(2)
    expect(screen.getAllByText('Sin grupos a su cargo').length).toBeGreaterThan(0)
    expect(screen.getAllByText('No seleccionable').length).toBeGreaterThan(0)
  })

  it('muestra el mensaje del servidor cuando el catálogo está vacío', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([], 'No hay materias disponibles en el catálogo institucional.'),
    })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    expect(screen.getByText('No hay materias en el catálogo')).toBeInTheDocument()
    expect(
      screen.getByText('No hay materias disponibles en el catálogo institucional.')
    ).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
    expect(screen.queryByText('No hay materias en el catálogo')).not.toBeInTheDocument()
  })

  it('pide la siguiente página al servidor en lugar de recortar la actual', async () => {
    const catalogo = Array.from({ length: 20 }, (_, index) =>
      makeSubject({ id_materia: index + 1, nombre: `Materia ${index + 1}` })
    )

    mockApiOnce({ body: subjectCatalogResponse(catalogo) })

    renderWithRouter(<SubjectsPage />, { route: '/materias' })
    await waitForLoad()

    expect(screen.getByText(/mostrando 1–8 de 20 materias/i)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /siguiente/i }))

    await screen.findByText(/mostrando 9–16 de 20 materias/i)

    const urls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.map(([url]) =>
      String(url)
    )

    expect(urls.some((url) => url.includes('page=2'))).toBe(true)
  })
})
