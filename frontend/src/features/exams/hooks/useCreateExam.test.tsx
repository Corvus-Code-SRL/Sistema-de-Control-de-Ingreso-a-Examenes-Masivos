import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/lib/api-client'
import { useCreateExam } from './useCreateExam'

const mocks = vi.hoisted(() => ({
  createExam: vi.fn(),
  setFields: vi.fn(),
  resetFormData: vi.fn(),
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
    resetFormData: mocks.resetFormData,
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

  it('evita solicitudes duplicadas mientras se registra el examen', async () => {
    const exam = {
        id_examen: 77,
        nombre_examen: 'Primer parcial',
    }

    let resolveRequest!: (value: typeof exam) => void

    const pendingRequest = new Promise<typeof exam>((resolve) => {
        resolveRequest = resolve
    })

    mocks.createExam.mockReturnValueOnce(pendingRequest)

    const onSuccess = vi.fn()
    const { result } = renderHook(() => useCreateExam(onSuccess))

    await act(async () => {
        const firstRequest = result.current.submitExam()
        const secondRequest = result.current.submitExam()

        expect(mocks.createExam).toHaveBeenCalledTimes(1)

        resolveRequest(exam)

        await Promise.all([firstRequest, secondRequest])
    })

    expect(mocks.createExam).toHaveBeenCalledTimes(1)
    expect(onSuccess).toHaveBeenCalledTimes(1)
    expect(result.current.submitting).toBe(false)
  })

  it('bloquea nuevos registros después del éxito hasta reiniciar', async () => {
    const exam = {
        id_examen: 77,
        nombre_examen: 'Primer parcial',
    }

    mocks.createExam
        .mockResolvedValueOnce(exam)
        .mockResolvedValueOnce({
        id_examen: 78,
        nombre_examen: 'Segundo parcial',
        })

    const onSuccess = vi.fn()
    const { result } = renderHook(() => useCreateExam(onSuccess))

    await act(async () => {
        await result.current.submitExam()
    })

    expect(mocks.createExam).toHaveBeenCalledTimes(1)

    // Intentar crear otra vez sin reiniciar.
    await act(async () => {
        await result.current.submitExam()
    })

    expect(mocks.createExam).toHaveBeenCalledTimes(1)

    // Simular la opción "Crear otro examen".
    act(() => {
        result.current.resetFormData()
    })

    expect(mocks.resetFormData).toHaveBeenCalledTimes(1)
    expect(result.current.warnings).toEqual([])
    expect(result.current.apiError).toBeNull()

    // Ahora sí puede realizar un nuevo registro.
    await act(async () => {
        await result.current.submitExam()
    })

    expect(mocks.createExam).toHaveBeenCalledTimes(2)
    expect(onSuccess).toHaveBeenCalledTimes(2)
  })

  it('elimina advertencias obsoletas si la confirmación devuelve HTTP 422', async () => {
    mocks.createExam
        .mockRejectedValueOnce(
        new ApiError(409, 'Debe confirmar las advertencias.', {
            capacidad_insuficiente: [
            'La capacidad seleccionada es insuficiente.',
            ],
        })
        )
        .mockRejectedValueOnce(
        new ApiError(422, 'Los datos del examen no son válidos.')
        )

    const onSuccess = vi.fn()
    const { result } = renderHook(() => useCreateExam(onSuccess))

    await act(async () => {
        await result.current.submitExam()
    })

    expect(result.current.warnings).toHaveLength(1)

    await act(async () => {
        await result.current.submitExam(true)
    })

    expect(result.current.warnings).toEqual([])
    expect(result.current.apiError).toBe(
        'Los datos del examen no son válidos.'
    )

    expect(onSuccess).not.toHaveBeenCalled()
    expect(result.current.submitting).toBe(false)
  })

  it('permite descartar advertencias sin modificar los campos', async () => {
    mocks.createExam.mockRejectedValueOnce(
        new ApiError(409, 'Debe confirmar las advertencias.', {
        superposicion_horario: [
            'Ya existe un examen en ese horario.',
        ],
        })
    )

    const { result } = renderHook(() => useCreateExam())

    const originalFormData = result.current.formData

    await act(async () => {
        await result.current.submitExam()
    })

    expect(result.current.warnings).toHaveLength(1)

    act(() => {
        result.current.dismissWarnings()
    })

    expect(result.current.warnings).toEqual([])
    expect(result.current.formData).toEqual(originalFormData)
    expect(mocks.setFields).not.toHaveBeenCalled()
    expect(mocks.resetFormData).not.toHaveBeenCalled()

    // Descartar las advertencias no debe registrar el examen.
    expect(mocks.createExam).toHaveBeenCalledTimes(1)
  })

  it('conserva múltiples advertencias devueltas por el backend', async () => {
    mocks.createExam.mockRejectedValueOnce(
        new ApiError(409, 'Revise las advertencias.', {
        nombre_duplicado: [
            'Ya existe un examen con ese nombre.',
        ],
        superposicion_horario: [
            'Ya existe un examen en ese horario.',
        ],
        capacidad_insuficiente: [
            'La capacidad de los ambientes es insuficiente.',
        ],
        })
    )

    const { result } = renderHook(() => useCreateExam())

    await act(async () => {
        await result.current.submitExam()
    })

    expect(result.current.warnings).toEqual([
        'Ya existe un examen con ese nombre.',
        'Ya existe un examen en ese horario.',
        'La capacidad de los ambientes es insuficiente.',
    ])

    expect(mocks.createExam).toHaveBeenCalledTimes(1)
  })
})
