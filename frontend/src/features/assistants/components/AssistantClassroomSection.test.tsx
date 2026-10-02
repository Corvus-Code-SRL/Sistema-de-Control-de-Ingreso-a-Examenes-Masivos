import { render, screen, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { examAssistantsData } from '@/test/assistantFixtures'
import { assistantsService } from '../services/assistantsService'
import { AssistantClassroomSection } from './AssistantClassroomSection'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    assignClassroom: vi.fn(),
  },
}))

const listExamAssistants = vi.mocked(assistantsService.listExamAssistants)

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
})
