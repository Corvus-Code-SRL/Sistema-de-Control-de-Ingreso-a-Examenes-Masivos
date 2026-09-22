import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { SubjectGroupsPage } from './SubjectGroupsPage'
import {
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
  subjectGroupsResponse,
} from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

const ROUTE = '/carreras/1/materias/10/grupos'
const PATH = '/carreras/:idCarrera/materias/:idMateria/grupos'

function renderPage() {
  return renderWithRouter(<SubjectGroupsPage />, { route: ROUTE, path: PATH })
}

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

describe('SubjectGroupsPage', () => {
  const materia = makeSubject()

  it('anuncia la carga antes de tener datos', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, [makeGroup()]) })

    renderPage()

    expect(screen.getByRole('status')).toBeInTheDocument()

    await waitForLoad()
  })

  it('lista los grupos del par con su docente dueño', async () => {
    mockApiOnce({
      body: subjectGroupsResponse(materia, [makeGroup(), makeForeignGroup()]),
    })

    renderPage()
    await waitForLoad()

    expect(screen.getAllByText('Grupo 1').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Grupo 2').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Victor Perez').length).toBeGreaterThan(0)
  })

  it('no ofrece el detalle de los grupos ajenos', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, [makeForeignGroup()]) })

    renderPage()
    await waitForLoad()

    expect(screen.queryByRole('link', { name: /ver detalles/i })).not.toBeInTheDocument()
    expect(screen.getAllByText('Solo lectura').length).toBeGreaterThan(0)
  })

  it('avisa, sin bloquear, cuando el docente no tiene grupos propios en el par', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, [makeForeignGroup()]) })

    renderPage()
    await waitForLoad()

    expect(screen.getByText('Usted no tiene grupos en esta materia')).toBeInTheDocument()
    expect(screen.getAllByText('Grupo 2').length).toBeGreaterThan(0)
  })

  it('muestra el estado vacío de un par sin grupos, sin lenguaje de error', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, []) })

    renderPage()
    await waitForLoad()

    expect(screen.getByText('Sin grupos registrados')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.queryByText(/no se pudo cargar/i)).not.toBeInTheDocument()
  })

  it('marca el grupo sin nómina en el listado', async () => {
    mockApiOnce({
      body: subjectGroupsResponse(materia, [makeGroup(), makeGroupWithoutRoster()]),
    })

    renderPage()
    await waitForLoad()

    expect(screen.getAllByText('Sin nómina').length).toBeGreaterThan(0)
  })

  it('muestra el mensaje del servidor cuando el par está inactivo', async () => {
    mockApiOnce({
      status: 422,
      body: { message: 'La materia seleccionada no está activa en esta carrera.' },
    })

    renderPage()
    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByText(/no está activa en esta carrera/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})
