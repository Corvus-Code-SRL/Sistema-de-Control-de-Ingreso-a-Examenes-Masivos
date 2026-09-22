import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {
  MemoryRouter,
  Route,
  Routes,
} from 'react-router-dom'
import {
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'
import { useAdminSubjects } from '../hooks/useAdminSubjects'
import { EditarMateriaPage } from './EditarMateriaPage'

vi.mock('../hooks/useAdminSubjects', () => ({
  useAdminSubjects: vi.fn(),
}))

const mockedUseAdminSubjects = vi.mocked(useAdminSubjects)

const reload = vi.fn()

const subjects = [
  {
    id_materia: 10,
    nombre: 'Bases de Datos I',
    codigo: '2008057',
  },
  {
    id_materia: 20,
    nombre: 'Cálculo II',
    codigo: '2008058',
  },
]

function renderPage(
  route = '/materias/10/editar'
) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <Routes>
        <Route
          path="/materias/:idMateria/editar"
          element={<EditarMateriaPage />}
        />

        <Route
          path="/materias"
          element={<div>Catálogo de materias</div>}
        />
      </Routes>
    </MemoryRouter>
  )
}

describe('EditarMateriaPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockedUseAdminSubjects.mockReturnValue({
      subjects,
      status: 'success',
      error: null,
      reload,
    })
  })

  it('precarga la materia indicada por la ruta', () => {
    renderPage('/materias/10/editar')

    expect(
      screen.getByRole('heading', {
        name: 'Editar materia',
      })
    ).toBeInTheDocument()

    expect(
      screen.getByLabelText(/Nombre de la materia/i)
    ).toHaveValue('Bases de Datos I')

    expect(
      screen.getByLabelText(/Código/i)
    ).toHaveValue('2008057')
  })

  it('selecciona la materia correcta cuando existen varias', () => {
    renderPage('/materias/20/editar')

    expect(
      screen.getByLabelText(/Nombre de la materia/i)
    ).toHaveValue('Cálculo II')

    expect(
      screen.getByLabelText(/Código/i)
    ).toHaveValue('2008058')
  })

  it('muestra un estado de no encontrado cuando el id no existe', () => {
    renderPage('/materias/999/editar')

    expect(
      screen.getByText('Materia no encontrada')
    ).toBeInTheDocument()

    expect(
      screen.queryByRole('button', {
        name: 'Guardar cambios',
      })
    ).not.toBeInTheDocument()
  })

  it('rechaza también un identificador inválido', () => {
    renderPage('/materias/abc/editar')

    expect(
      screen.getByText('Materia no encontrada')
    ).toBeInTheDocument()
  })

  it('vuelve al catálogo al cancelar sin actualizar la materia', async () => {
    const user = userEvent.setup()

    renderPage()

    await user.click(
      screen.getByRole('button', {
        name: 'Cancelar',
      })
    )

    expect(
      screen.getByText('Catálogo de materias')
    ).toBeInTheDocument()
  })
})