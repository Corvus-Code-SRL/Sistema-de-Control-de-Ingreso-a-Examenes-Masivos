import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/lib/api-client'
import { useCreateExam } from './useCreateExam'

const mocks = vi.hoisted(() => ({
  createExam: vi.fn(),
  setFields: vi.fn(),
}))

vi.mock('../services/examsService', () => ({
  examsService: {
    createExam: mocks.createExam,
  },
}))

vi.mock('./useExamFormOptions', () => ({
  useExamFormOptions: () => ({
    options: {
      materias: [],
      ambientes: [],
      grupos: [],
    },
    loading: false,
    error: null,
  }),
}))

vi.mock('./useExamFormState', () => ({
  useExamFormState: () => ({
    formData: {
      nombre_examen: 'Primer parcial',
      materia: {
        id_carrera: 2,
        id_materia: 3,
      },
      categoria: 'REGULAR',
      fecha: '2026-10-20',
      hora_inicio: '08:00',
      duracion: 90,
      ambientes: [1, 2],
      grupos: [101],
      normas: '',
    },
    updateFormData: mocks.setFields,
    resetFormData: vi.fn(),
  }),
}))

vi.mock('../utils/examValidators', () => ({
  validateExamForm: () => ({
    errors: {},
    warnings: {},
  }),
  validateGroupsStep: () => ({
    errors: {},
    warnings: {},
  }),
}))

describe('useCreateExam - confirmación de advertencias (HU-024)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('muestra las advertencias estructuradas del HTTP 409', async () => {
    mocks.createExam.mockRejectedValueOnce(
      new ApiError(
        409,
        'El examen requiere confirmación.',
        {
          capacidad_insuficiente: [
            'La capacidad es menor que la cantidad de estudiantes.',
          ],
        }
      )
    )

    const { result } = renderHook(() => useCreateExam())

    await act(async () => {
      await result.current.submitExam()
    })

    expect(result.current.warnings).toEqual([
      'La capacidad es menor que la cantidad de estudiantes.',
    ])

    expect(mocks.createExam).toHaveBeenCalledWith(
      expect.objectContaining({
        confirmar_advertencias: undefined,
      })
    )
  })

  it('permite confirmar y reenvía el examen con la bandera correcta', async () => {
    const exam = {
      id_examen: 77,
      nombre_examen: 'Primer parcial',
    }

    mocks.createExam
      .mockRejectedValueOnce(
        new ApiError(409, 'Confirme las advertencias.', {
          superposicion_horario: [
            'Ya tiene un examen en ese horario.',
          ],
        })
      )
      .mockResolvedValueOnce(exam)

    const onSuccess = vi.fn()
    const { result } = renderHook(() => useCreateExam(onSuccess))

    await act(async () => {
      await result.current.submitExam()
    })

    expect(result.current.warnings).toHaveLength(1)

    await act(async () => {
      await result.current.submitExam(true)
    })

    expect(mocks.createExam).toHaveBeenCalledTimes(2)

    expect(mocks.createExam).toHaveBeenLastCalledWith(
      expect.objectContaining({
        confirmar_advertencias: true,
      })
    )

    expect(result.current.warnings).toEqual([])
    expect(onSuccess).toHaveBeenCalledWith(exam)
  })

  it('conserva el mensaje general cuando el HTTP 409 no incluye errores', async () => {
    mocks.createExam.mockRejectedValueOnce(
      new ApiError(409, 'Debe revisar el horario del examen.')
    )

    const { result } = renderHook(() => useCreateExam())

    await act(async () => {
      await result.current.submitExam()
    })

    expect(result.current.warnings).toEqual([
      'Debe revisar el horario del examen.',
    ])

    act(() => {
      result.current.updateFormData({
        nombre_examen: 'Segundo parcial',
      })
    })

    expect(result.current.warnings).toEqual([])
  })
})
