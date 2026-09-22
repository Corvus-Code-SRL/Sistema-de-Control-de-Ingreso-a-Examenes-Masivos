import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useMyCourses } from './useMyCourses'
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

describe('useMyCourses', () => {
  const basesDeDatos = makeSubject()
  const programacion = makeSubject({
    id_materia: 20,
    id_carrera: informatica.id_carrera,
    nombre: 'Introduccion a la Programacion',
    codigo: '2008019',
    carrera: informatica,
    cantidad_grupos: 1,
  })

  it('reúne los grupos propios de todas las carreras', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos, programacion]) },
      {
        matches: (url) => url.includes('/carreras/1/materias/10/grupos'),
        body: subjectGroupsResponse(basesDeDatos, [makeGroup(), makeForeignGroup()]),
      },
      {
        matches: (url) => url.includes('/carreras/2/materias/20/grupos'),
        body: subjectGroupsResponse(programacion, [
          makeGroup({ id_grupo: 400, num_grupo: '2', cantidad_estudiantes: 312 }),
        ]),
      },
    ])

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.courses).toHaveLength(2)
    expect(result.current.courses.map((course) => course.subject.carrera.codigo)).toEqual([
      'SIS',
      'INF',
    ])
    expect(result.current.error).toBeNull()
  })

  it('descarta los grupos de otros docentes', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos]) },
      {
        matches: matchers.groupsOfPair,
        body: subjectGroupsResponse(basesDeDatos, [makeForeignGroup(), makeForeignGroup({ id_grupo: 201 })]),
      },
    ])

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.courses).toHaveLength(0)
    expect(result.current.isEmpty).toBe(true)
    expect(result.current.error).toBeNull()
  })

  it('trata un docente sin cursos como estado vacío', async () => {
    mockApi([
      {
        matches: matchers.catalog,
        body: subjectCatalogResponse([
          makeSubject({ es_mia: false, cantidad_grupos: 0 }),
        ]),
      },
    ])

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isEmpty).toBe(true)
    expect(result.current.error).toBeNull()
    expect(result.current.courses).toHaveLength(0)
  })

  it('incluye un curso sin nómina', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos]) },
      {
        matches: matchers.groupsOfPair,
        body: subjectGroupsResponse(basesDeDatos, [makeGroupWithoutRoster()]),
      },
    ])

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.courses).toHaveLength(1)
    expect(result.current.courses[0].group.cantidad_estudiantes).toBe(0)
    expect(result.current.isEmpty).toBe(false)
  })

  it('omite el par que falla y conserva el resto de los cursos', async () => {
    mockApi([
      { matches: matchers.catalog, body: subjectCatalogResponse([basesDeDatos, programacion]) },
      {
        matches: (url) => url.includes('/carreras/1/materias/10/grupos'),
        body: subjectGroupsResponse(basesDeDatos, [makeGroup()]),
      },
      {
        matches: (url) => url.includes('/carreras/2/materias/20/grupos'),
        status: 422,
        body: { message: 'La materia seleccionada no está activa en esta carrera.' },
      },
    ])

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.courses).toHaveLength(1)
    expect(result.current.error).toBeNull()
  })

  it('expone el error cuando falla el catálogo, del que depende todo lo demás', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    const { result } = renderHook(() => useMyCourses())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.error?.message).toBe('Error interno del servidor.')
    expect(result.current.isEmpty).toBe(false)
    expect(result.current.courses).toHaveLength(0)
  })
})
