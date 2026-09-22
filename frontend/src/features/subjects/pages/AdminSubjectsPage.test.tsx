import {
  screen,
  waitForElementToBeRemoved,
} from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AdminSubjectsPage } from './AdminSubjectsPage'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

describe('AdminSubjectsPage', () => {
  it('muestra el catálogo administrativo de materias', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_materia: 10,
            nombre: 'Bases de Datos I',
            codigo: '2008057',
            descripcion: null,
            estado: 'ACTIVO',
          },
          {
            id_materia: 20,
            nombre: 'Calculo II',
            codigo: '2008058',
            descripcion: null,
            estado: 'INACTIVO',
          },
        ],
      },
    })

    renderWithRouter(<AdminSubjectsPage />, {
      route: '/materias',
    })

    await waitForLoad()

    expect(
      screen.getByRole('heading', { name: 'Materias' })
    ).toBeInTheDocument()

    expect(screen.getByText('Bases de Datos I')).toBeInTheDocument()
    expect(screen.getByText('Calculo II')).toBeInTheDocument()

    expect(
      screen.getByRole('link', { name: 'Editar Bases de Datos I' })
    ).toHaveAttribute('href', '/materias/10/editar')
  })

  it('muestra el estado vacío cuando no existen materias', async () => {
    mockApiOnce({
      body: {
        data: [],
      },
    })

    renderWithRouter(<AdminSubjectsPage />, {
      route: '/materias',
    })

    await waitForLoad()

    expect(
      screen.getByText('Aún no hay materias registradas')
    ).toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiOnce({
      status: 500,
      body: { message: 'Error interno del servidor.' },
    })

    renderWithRouter(<AdminSubjectsPage />, {
      route: '/materias',
    })

    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
  })
})