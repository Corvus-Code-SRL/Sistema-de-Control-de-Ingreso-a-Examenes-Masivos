import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { actualizarMateria } from '../services/subjectsService'
import { useUpdateSubject } from './useUpdateSubject'

vi.mock('../services/subjectsService', () => ({
  actualizarMateria: vi.fn(),
}))

const mockedActualizarMateria = vi.mocked(actualizarMateria)

describe('useUpdateSubject', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('actualiza una materia y devuelve los datos actualizados', async () => {
    mockedActualizarMateria.mockResolvedValue({
      data: {
        id_materia: 10,
        nombre: 'Bases de Datos II',
        codigo: '2008057',
        descripcion: null,
        estado: 'ACTIVO',
      },
      mensaje: 'Materia actualizada correctamente.',
    })

    const { result } = renderHook(() => useUpdateSubject())

    let updated = null

    await act(async () => {
      updated = await result.current.submit(10, {
        nombre: 'Bases de Datos II',
        codigo: '2008057',
      })
    })

    expect(mockedActualizarMateria).toHaveBeenCalledWith(10, {
      nombre: 'Bases de Datos II',
      codigo: '2008057',
    })

    expect(updated).toEqual({
      id_materia: 10,
      nombre: 'Bases de Datos II',
      codigo: '2008057',
      descripcion: null,
      estado: 'ACTIVO',
    })

    expect(result.current.status).toBe('success')
    expect(result.current.error).toBeNull()
  })

  it('conserva el ApiError cuando el backend rechaza la actualización', async () => {
    const apiError = new ApiError(
      422,
      'Los datos proporcionados no son válidos.',
      {
        codigo: ['Ya existe una materia registrada con el código 2008057.'],
      }
    )

    mockedActualizarMateria.mockRejectedValue(apiError)

    const { result } = renderHook(() => useUpdateSubject())

    let updated = null

    await act(async () => {
      updated = await result.current.submit(10, {
        nombre: 'Bases de Datos',
        codigo: '2008057',
      })
    })

    expect(updated).toBeNull()
    expect(result.current.status).toBe('error')
    expect(result.current.error).toBe(apiError)
  })

  it('restablece el estado después de un error', async () => {
    mockedActualizarMateria.mockRejectedValue(
      new ApiError(500, 'Error interno del servidor.')
    )

    const { result } = renderHook(() => useUpdateSubject())

    await act(async () => {
      await result.current.submit(10, {
        nombre: 'Bases de Datos',
        codigo: '2008057',
      })
    })

    expect(result.current.status).toBe('error')

    act(() => {
      result.current.reset()
    })

    expect(result.current.status).toBe('idle')
    expect(result.current.error).toBeNull()
  })
})