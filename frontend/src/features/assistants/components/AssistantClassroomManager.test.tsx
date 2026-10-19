import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { aula691A, daniela, examAssistantsData, maria } from '@/test/assistantFixtures'
import { assistantsService } from '../services/assistantsService'
import type { ExamAssistantsData } from '../types/assistant.types'
import { AssistantClassroomManager } from './AssistantClassroomManager'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    assignClassroom: vi.fn(),
  },
}))

const listExamAssistants = vi.mocked(assistantsService.listExamAssistants)
const assignClassroom = vi.mocked(assistantsService.assignClassroom)

beforeAll(() => {
  Element.prototype.hasPointerCapture ??= () => false
  Element.prototype.setPointerCapture ??= () => {}
  Element.prototype.releasePointerCapture ??= () => {}
  Element.prototype.scrollIntoView ??= () => {}
})

function mockBackend(initial: ExamAssistantsData) {
  let state = initial
  listExamAssistants.mockImplementation(async () => state)
  assignClassroom.mockImplementation(async (_examId, userId, roomId) => {
    const previous = state.auxiliares.find((assistant) => assistant.id_usuario === userId)
    if (!previous) throw new Error('Auxiliar inexistente en la prueba')
    const updated = {
      ...previous,
      id_ambiente: roomId,
      ambiente: state.ambientes.find((room) => room.id_ambiente === roomId) ?? null,
    }
    state = {
      ...state,
      auxiliares: state.auxiliares.map((assistant) =>
        assistant.id_usuario === userId ? updated : assistant
      ),
    }
    return updated
  })
}

describe('AssistantClassroomManager: HU-030', () => {
  beforeEach(() => vi.clearAllMocks())

  it('muestra la lista y los selectores en el modal', async () => {
    const user = userEvent.setup()
    mockBackend(examAssistantsData())
    render(<AssistantClassroomManager examId={7} />)

    await user.click(screen.getByRole('button', { name: 'Asignar ambientes' }))
    const modal = await screen.findByRole('dialog', { name: 'Asignar ambientes' })

    expect(within(modal).getByRole('combobox', { name: 'Ambiente de Daniela Ferrufino Soliz' })).toBeEnabled()
    expect(within(modal).getByRole('combobox', { name: 'Ambiente de María López Arnez' })).toBeEnabled()
    expect(screen.getByText('1 auxiliar sin ambiente')).toBeVisible()
  })

  it('guarda solo al pulsar Guardar, cierra el modal y actualiza el detalle', async () => {
    const user = userEvent.setup()
    mockBackend(examAssistantsData())
    render(<AssistantClassroomManager examId={7} />)

    await user.click(screen.getByRole('button', { name: 'Asignar ambientes' }))
    const modal = await screen.findByRole('dialog', { name: 'Asignar ambientes' })
    await user.click(within(modal).getByRole('combobox', { name: 'Ambiente de Daniela Ferrufino Soliz' }))
    await user.click(await screen.findByRole('option', { name: 'Aula 691A' }))

    expect(assignClassroom).not.toHaveBeenCalled()
    await user.click(within(modal).getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Asignar ambientes' })).not.toBeInTheDocument())
    expect(assignClassroom).toHaveBeenCalledWith(7, daniela.id_usuario, aula691A.id_ambiente)
    expect(await screen.findByText(/Daniela Ferrufino Soliz → Aula 691A/)).toBeVisible()
  })

  it('cuando solo hay un ambiente, informa el ambiente por defecto sin ofrecer selectores', async () => {
    const user = userEvent.setup()
    mockBackend(examAssistantsData({ ambientes: [aula691A], auxiliares: [daniela, { ...maria, id_ambiente: null, ambiente: null }] }))
    render(<AssistantClassroomManager examId={7} />)

    await user.click(screen.getByRole('button', { name: 'Asignar ambientes' }))
    const modal = await screen.findByRole('dialog', { name: 'Asignar ambientes' })
    expect(within(modal).getByText('Todos los auxiliares controlan en el Aula 691A.')).toBeVisible()
    expect(within(modal).queryByRole('combobox')).not.toBeInTheDocument()
    expect(within(modal).queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
  })

  it('al iniciar el ingreso, permite consultar sin modificar', async () => {
    const user = userEvent.setup()
    mockBackend(examAssistantsData({ estado: 'EN_INGRESO', editable: false }))
    render(<AssistantClassroomManager examId={7} />)

    await user.click(await screen.findByRole('button', { name: 'Ver ambientes' }))
    const modal = await screen.findByRole('dialog', { name: 'Asignar ambientes' })
    expect(within(modal).getByText(/ya no permite cambiar asignaciones/i)).toBeVisible()
    expect(within(modal).queryByRole('combobox')).not.toBeInTheDocument()
    expect(within(modal).queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
  })
})
