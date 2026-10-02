import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { aula691A, daniela, examAssistantsData, jorge, maria } from '@/test/assistantFixtures'
import { assistantsService } from '../services/assistantsService'
import type { ExamAssistant, ExamAssistantsData } from '../types/assistant.types'
import { AssistantClassroomSection } from './AssistantClassroomSection'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    assignClassroom: vi.fn(),
  },
}))

const listExamAssistants = vi.mocked(assistantsService.listExamAssistants)
const assignClassroom = vi.mocked(assistantsService.assignClassroom)

type User = ReturnType<typeof userEvent.setup>

/** jsdom no implementa la captura de puntero ni scrollIntoView, que Radix Select usa. */
beforeAll(() => {
  Element.prototype.hasPointerCapture ??= () => false
  Element.prototype.setPointerCapture ??= () => {}
  Element.prototype.releasePointerCapture ??= () => {}
  Element.prototype.scrollIntoView ??= () => {}
})

/** El servicio mockeado se comporta como el backend: guarda y devuelve lo guardado. */
function emulateBackend(initial: ExamAssistantsData) {
  let state = initial

  listExamAssistants.mockImplementation(async () => state)
  assignClassroom.mockImplementation(async (_examId, userId, classroomId) => {
    const current = state.auxiliares.find((assistant) => assistant.id_usuario === userId)
    if (!current) throw new Error(`Auxiliar no habilitado en la prueba: ${userId}`)

    const updated: ExamAssistant = {
      ...current,
      id_ambiente: classroomId,
      ambiente: state.ambientes.find((classroom) => classroom.id_ambiente === classroomId) ?? null,
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

function classroomSelect(assistantName: string) {
  return screen.getByRole('combobox', { name: `Ambiente de ${assistantName}` })
}

/** Abre el selector del auxiliar y elige el ambiente, como lo haría el docente. */
async function chooseClassroom(user: User, assistantName: string, classroomName: string) {
  await waitFor(() => expect(classroomSelect(assistantName)).toBeEnabled())
  await user.click(classroomSelect(assistantName))
  await user.click(await screen.findByRole('option', { name: classroomName }))
}

/** Elige el ambiente y espera a que el listado recargado lo muestre guardado. */
async function assign(user: User, assistantName: string, classroomName: string) {
  await chooseClassroom(user, assistantName, classroomName)
  await waitFor(() => expect(classroomSelect(assistantName)).toHaveTextContent(classroomName))
}

describe('AssistantClassroomSection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('en un examen programado ofrece un selector por auxiliar y marca a quien no tiene ambiente', async () => {
    listExamAssistants.mockResolvedValue(examAssistantsData())

    render(<AssistantClassroomSection examId={7} />)

    expect(
      await screen.findByRole('combobox', { name: 'Ambiente de María López Arnez' })
    ).toBeEnabled()
    expect(
      screen.getByRole('combobox', { name: 'Ambiente de Daniela Ferrufino Soliz' })
    ).toHaveTextContent('Sin asignar')
    expect(screen.getByText('Sin ambiente')).toBeVisible()
    expect(screen.getByText('Cada cambio se guarda al momento')).toBeVisible()
    expect(listExamAssistants).toHaveBeenCalledWith(7, expect.any(AbortSignal))
  })

  it('con el control de ingreso abierto muestra las asignaciones sin selectores', async () => {
    listExamAssistants.mockResolvedValue(examAssistantsData({ editable: false }))

    render(<AssistantClassroomSection examId={7} />)

    expect(await screen.findByText('El control de ingreso ya se abrió')).toBeVisible()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    // Sin ambiente: solo el aviso. Con ambiente: el aula como texto, sin selector.
    const [unassignedRow, assignedRow] = screen.getAllByRole('listitem')
    expect(within(unassignedRow).getByText('Sin ambiente')).toBeVisible()
    expect(within(assignedRow).getByText('Auditorio FCyT')).toBeVisible()
  })

  it('explica cuando el examen no tiene auxiliares habilitados', async () => {
    listExamAssistants.mockResolvedValue(examAssistantsData({ auxiliares: [] }))

    render(<AssistantClassroomSection examId={7} />)

    expect(await screen.findByText('Este examen no tiene auxiliares habilitados')).toBeVisible()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('prueba de aceptación: asigna ambientes a tres auxiliares y reasigna uno', async () => {
    const user = userEvent.setup()
    emulateBackend(
      examAssistantsData({
        auxiliares: [daniela, jorge, { ...maria, id_ambiente: null, ambiente: null }],
      })
    )

    render(<AssistantClassroomSection examId={7} />)
    await screen.findByRole('combobox', { name: 'Ambiente de María López Arnez' })

    await assign(user, 'María López Arnez', 'Auditorio FCyT')
    await assign(user, 'Jorge Rocha Vidal', 'Aula 691A')
    await assign(user, 'Daniela Ferrufino Soliz', 'Auditorio FCyT')
    await assign(user, 'Daniela Ferrufino Soliz', 'Aula 691A')

    expect(assignClassroom).toHaveBeenCalledTimes(4)
    expect(assignClassroom).toHaveBeenLastCalledWith(7, daniela.id_usuario, aula691A.id_ambiente)
    expect(classroomSelect('María López Arnez')).toHaveTextContent('Auditorio FCyT')
    expect(classroomSelect('Jorge Rocha Vidal')).toHaveTextContent('Aula 691A')
    expect(await screen.findByText('Daniela Ferrufino Soliz controlará en Aula 691A.')).toBeVisible()
    expect(screen.queryByText('Sin ambiente')).not.toBeInTheDocument()
  })

  it('si el control de ingreso se abrió mientras tanto, lo explica en un diálogo y conserva la asignación', async () => {
    const user = userEvent.setup()
    const message =
      'El control de ingreso del examen ya se inició: los ambientes de los auxiliares quedaron fijos.'
    listExamAssistants.mockResolvedValue(examAssistantsData())
    assignClassroom.mockRejectedValue(new ApiError(409, message))

    render(<AssistantClassroomSection examId={7} />)
    await screen.findByRole('combobox', { name: 'Ambiente de Daniela Ferrufino Soliz' })
    await chooseClassroom(user, 'Daniela Ferrufino Soliz', 'Auditorio FCyT')

    const dialog = await screen.findByRole('dialog', { name: 'No se guardó la asignación' })
    expect(within(dialog).getByText(message)).toBeVisible()
    expect(within(dialog).getByText('La asignación anterior se conserva.')).toBeVisible()

    await user.click(within(dialog).getByRole('button', { name: 'Entendido' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(classroomSelect('Daniela Ferrufino Soliz')).toHaveTextContent('Sin asignar')
  })
})
