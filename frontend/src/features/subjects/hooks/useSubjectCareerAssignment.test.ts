import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { assignSubjectToCareer } from '../services/subjectsService'
import { useSubjectCareerAssignment } from './useSubjectCareerAssignment'

vi.mock('../services/subjectsService', () => ({
  assignSubjectToCareer: vi.fn(),
}))

const mockedAssignSubjectToCareer = vi.mocked(assignSubjectToCareer)

describe('useSubjectCareerAssignment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('asigna una materia a una carrera y devuelve la relacion creada', async () => {
    mockedAssignSubjectToCareer.mockResolvedValue({
      data: {
        id_carrera: 3,
        id_materia: 10,
        estado: 'ACTIVO',
        carrera: {
          id_carrera: 3,
          nombre: 'Ingenieria de Sistemas',
          codigo: 'SIS',
          id_facultad: 1,
        },
        materia: {
          id_materia: 10,
          nombre: 'Inteligencia Artificial',
          codigo: '2008001',
          descripcion: null,
          estado: 'ACTIVO',
        },
      },
      mensaje: 'Materia asignada correctamente.',
    })

    const { result } = renderHook(() => useSubjectCareerAssignment())

    let assignment = null

    await act(async () => {
      assignment = await result.current.submit(3, {
        id_materia: 10,
      })
    })

    expect(mockedAssignSubjectToCareer).toHaveBeenCalledWith(3, {
      id_materia: 10,
    })

    expect(assignment).toEqual({
      id_carrera: 3,
      id_materia: 10,
      estado: 'ACTIVO',
      carrera: {
        id_carrera: 3,
        nombre: 'Ingenieria de Sistemas',
        codigo: 'SIS',
        id_facultad: 1,
      },
      materia: {
        id_materia: 10,
        nombre: 'Inteligencia Artificial',
        codigo: '2008001',
        descripcion: null,
        estado: 'ACTIVO',
      },
    })

    expect(result.current.status).toBe('success')
    expect(result.current.error).toBeNull()
  })

  it('conserva el ApiError cuando el backend rechaza la asignacion', async () => {
    const apiError = new ApiError(
      422,
      'Los datos proporcionados no son validos.',
      {
        id_materia: [
          'La materia seleccionada ya se encuentra asignada a esta carrera.',
        ],
      }
    )

    mockedAssignSubjectToCareer.mockRejectedValue(apiError)

    const { result } = renderHook(() => useSubjectCareerAssignment())

    let assignment = null

    await act(async () => {
      assignment = await result.current.submit(3, {
        id_materia: 10,
      })
    })

    expect(assignment).toBeNull()
    expect(result.current.status).toBe('error')
    expect(result.current.error).toBe(apiError)
    expect(result.current.error?.status).toBe(422)
    expect(result.current.error?.errors.id_materia).toEqual([
      'La materia seleccionada ya se encuentra asignada a esta carrera.',
    ])
  })

  it('convierte un error inesperado en ApiError', async () => {
    mockedAssignSubjectToCareer.mockRejectedValue(
      new Error('Fallo inesperado')
    )

    const { result } = renderHook(() => useSubjectCareerAssignment())

    let assignment = null

    await act(async () => {
      assignment = await result.current.submit(3, {
        id_materia: 10,
      })
    })

    expect(assignment).toBeNull()
    expect(result.current.status).toBe('error')
    expect(result.current.error).toBeInstanceOf(ApiError)
    expect(result.current.error?.status).toBe(0)
    expect(result.current.error?.message).toBe('Fallo inesperado')
  })

  it('restablece el estado despues de un error', async () => {
    mockedAssignSubjectToCareer.mockRejectedValue(
      new ApiError(500, 'Error interno del servidor.')
    )

    const { result } = renderHook(() => useSubjectCareerAssignment())

    await act(async () => {
      await result.current.submit(3, {
        id_materia: 10,
      })
    })

    expect(result.current.status).toBe('error')
    expect(result.current.error).not.toBeNull()

    act(() => {
      result.current.reset()
    })

    expect(result.current.status).toBe('idle')
    expect(result.current.error).toBeNull()
  })
})