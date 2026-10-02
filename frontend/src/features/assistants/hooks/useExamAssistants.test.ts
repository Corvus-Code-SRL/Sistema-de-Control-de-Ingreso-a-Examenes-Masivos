import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { auditorio, daniela, examAssistantsData } from '@/test/assistantFixtures'
import { assistantsService } from '../services/assistantsService'
import { useExamAssistants } from './useExamAssistants'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    assignClassroom: vi.fn(),
  },
}))

const listExamAssistants = vi.mocked(assistantsService.listExamAssistants)
const assignClassroom = vi.mocked(assistantsService.assignClassroom)

async function renderLoaded() {
  const hook = renderHook(() => useExamAssistants(7))
  await waitFor(() => expect(hook.result.current.status).toBe('success'))
  return hook
}

describe('useExamAssistants', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listExamAssistants.mockResolvedValue(examAssistantsData())
  })

  it('asigna el ambiente, avisa con el nombre del auxiliar y vuelve a pedir el listado', async () => {
    assignClassroom.mockResolvedValue({ ...daniela, id_ambiente: 11, ambiente: auditorio })
    const { result } = await renderLoaded()

    await act(async () => {
      await result.current.assign(daniela, 11)
    })

    expect(assignClassroom).toHaveBeenCalledWith(7, daniela.id_usuario, 11)
    expect(result.current.notice).toEqual({
      title: 'Ambiente asignado',
      description: 'Daniela Ferrufino Soliz controlará en Auditorio FCyT.',
    })
    expect(result.current.savingUserId).toBeNull()
    await waitFor(() => expect(listExamAssistants).toHaveBeenCalledTimes(2))
  })

  it('si el control de ingreso ya se abrió guarda el mensaje para el diálogo', async () => {
    const message = 'El control de ingreso del examen ya se inició: los ambientes de los auxiliares quedaron fijos.'
    assignClassroom.mockRejectedValue(new ApiError(409, message))
    const { result } = await renderLoaded()

    await act(async () => {
      await result.current.assign(daniela, 11)
    })

    expect(result.current.lockedMessage).toBe(message)
    expect(result.current.assignError).toBeNull()
    await waitFor(() => expect(listExamAssistants).toHaveBeenCalledTimes(2))
  })

  it('muestra el motivo de cualquier otro rechazo', async () => {
    assignClassroom.mockRejectedValue(
      new ApiError(422, 'El ambiente seleccionado no pertenece a este examen.')
    )
    const { result } = await renderLoaded()

    await act(async () => {
      await result.current.assign(daniela, 99)
    })

    expect(result.current.assignError).toBe('El ambiente seleccionado no pertenece a este examen.')
    expect(result.current.lockedMessage).toBeNull()
  })
})
