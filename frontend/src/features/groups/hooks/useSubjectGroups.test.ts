import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useSubjectGroups } from './useSubjectGroups'
import {
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
  subjectGroupsResponse,
} from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'

describe('useSubjectGroups', () => {
  const materia = makeSubject()

  it('devuelve todos los grupos del par, también los de otros docentes', async () => {
    mockApiOnce({
      body: subjectGroupsResponse(materia, [makeGroup(), makeForeignGroup()]),
    })

    const { result } = renderHook(() => useSubjectGroups(1, 10))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.groups).toHaveLength(2)
    expect(result.current.groups.map((group) => group.es_mio)).toEqual([true, false])
    expect(result.current.hasNoOwnGroups).toBe(false)
  })

  it('trata un par sin grupos como estado vacío, no como error', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, []) })

    const { result } = renderHook(() => useSubjectGroups(1, 10))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isEmpty).toBe(true)
    expect(result.current.error).toBeNull()
    expect(result.current.groups).toHaveLength(0)
    expect(result.current.subject).not.toBeNull()
  })

  it('señala que el docente no tiene grupos propios aunque el par sí los tenga', async () => {
    mockApiOnce({ body: subjectGroupsResponse(materia, [makeForeignGroup()]) })

    const { result } = renderHook(() => useSubjectGroups(1, 10))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isEmpty).toBe(false)
    expect(result.current.hasNoOwnGroups).toBe(true)
    expect(result.current.groups).toHaveLength(1)
  })

  it('conserva el grupo sin nómina como un grupo con cero inscritos', async () => {
    mockApiOnce({
      body: subjectGroupsResponse(materia, [makeGroup(), makeGroupWithoutRoster()]),
    })

    const { result } = renderHook(() => useSubjectGroups(1, 10))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    const sinNomina = result.current.groups.find((group) => group.num_grupo === '3')

    expect(sinNomina?.cantidad_estudiantes).toBe(0)
    expect(result.current.isEmpty).toBe(false)
  })

  it('expone el mensaje del par inactivo que devuelve el servidor', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'La materia seleccionada no está activa en esta carrera.',
        errors: { id_materia: ['La materia seleccionada no está activa en esta carrera.'] },
      },
    })

    const { result } = renderHook(() => useSubjectGroups(1, 10))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.error?.isValidation).toBe(true)
    expect(result.current.error?.message).toMatch(/no está activa/i)
    expect(result.current.isEmpty).toBe(false)
    expect(result.current.groups).toHaveLength(0)
  })

  it('expone el 404 cuando el par no existe', async () => {
    mockApiOnce({
      status: 404,
      body: { message: 'No existe la materia indicada dentro de esa carrera.' },
    })

    const { result } = renderHook(() => useSubjectGroups(99, 99))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.error?.isNotFound).toBe(true)
    expect(result.current.isEmpty).toBe(false)
  })
})
