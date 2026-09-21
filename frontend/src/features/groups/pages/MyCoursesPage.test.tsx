import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { MyCoursesPage } from './MyCoursesPage'
import {
  informatica,
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
  subjectCatalogResponse,
  subjectGroupsResponse,
} from '@/test/fixtures'
import { matchers, mockApi, mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

describe('MyCoursesPage', () => {
  const basesDeDatos = makeSubject()

  it('muestra una tarjeta por cada curso, con su carrera', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos]) },
      {
        matches: matchers.groupsOfPair,
        body: subjectGroupsResponse(basesDeDatos, [
          makeGroup(),
          makeGroup({ id_grupo: 101, num_grupo: '4' }),
        ]),
      },
    ])

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByRole('link', { name: /bases de datos i · grupo 1/i })).toHaveAttribute(
      'href',
      '/cursos/100'
    )
    expect(screen.getByRole('link', { name: /bases de datos i · grupo 4/i })).toBeInTheDocument()
    expect(screen.getAllByText('Ingenieria de Sistemas').length).toBeGreaterThan(0)
  })

  it('distingue la misma materia dictada en dos carreras', async () => {
    const enInformatica = makeSubject({
      id_materia: 20,
      id_carrera: informatica.id_carrera,
      carrera: informatica,
    })

    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos, enInformatica]) },
      {
        matches: (url) => url.includes('/carreras/1/'),
        body: subjectGroupsResponse(basesDeDatos, [makeGroup()]),
      },
      {
        matches: (url) => url.includes('/carreras/2/'),
        body: subjectGroupsResponse(enInformatica, [makeGroup({ id_grupo: 500 })]),
      },
    ])

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByText('Ingenieria de Sistemas')).toBeInTheDocument()
    expect(screen.getByText('Ingenieria Informatica')).toBeInTheDocument()
  })

  it('muestra el estado vacío cuando el docente no tiene cursos', async () => {
    mockApi([
      {
        matches: matchers.catalog,
        body: subjectCatalogResponse([makeSubject({ es_mia: false, cantidad_grupos: 0 })]),
      },
    ])

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByText('Aún no tiene cursos')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: /ir a materias/i }).length).toBeGreaterThan(0)
  })

  it('no incluye los grupos de otros docentes', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos]) },
      {
        matches: matchers.groupsOfPair,
        body: subjectGroupsResponse(basesDeDatos, [makeGroup(), makeForeignGroup()]),
      },
    ])

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByRole('link', { name: /grupo 1/i })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /grupo 2/i })).not.toBeInTheDocument()
  })

  it('marca el curso sin nómina', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos]) },
      {
        matches: matchers.groupsOfPair,
        body: subjectGroupsResponse(basesDeDatos, [makeGroupWithoutRoster()]),
      },
    ])

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByText('Sin nómina')).toBeInTheDocument()
    expect(screen.getByText('Sin inscritos')).toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    renderWithRouter(<MyCoursesPage />, { route: '/mis-cursos' })
    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByText('Error interno del servidor.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
    expect(screen.queryByText('Aún no tiene cursos')).not.toBeInTheDocument()
  })
})
