import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { assignedExam, unassignedExam } from '@/test/assistantFixtures'
import { renderWithRouter } from '@/test/render'
import { assistantsService } from '../services/assistantsService'
import { MyAssignmentsPage } from './MyAssignmentsPage'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    assignClassroom: vi.fn(),
    listMyExams: vi.fn(),
  },
}))

const listMyExams = vi.mocked(assistantsService.listMyExams)

describe('MyAssignmentsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('muestra cada examen con su ambiente o el aviso de que aún no lo tiene', async () => {
    listMyExams.mockResolvedValue([assignedExam, unassignedExam])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    expect(await screen.findByText('1er Parcial BD I')).toBeVisible()
    expect(screen.getByText('Auditorio FCyT')).toBeVisible()
    expect(screen.getByText(/asignado por el docente/)).toBeVisible()
    expect(screen.getByText('Parcial práctico IP')).toBeVisible()
    expect(screen.getByText(/Aún sin ambiente asignado/)).toBeVisible()
  })

  it('es de solo lectura: el auxiliar no puede elegir ni cambiar su ambiente', async () => {
    listMyExams.mockResolvedValue([assignedExam])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    expect(await screen.findByText('1er Parcial BD I')).toBeVisible()
    // El único selector de la pantalla es el de «Vista de desarrollo» del sidebar.
    expect(screen.queryByRole('combobox', { name: /ambiente/i })).not.toBeInTheDocument()
  })

  it('explica cuando no tiene exámenes por controlar', async () => {
    listMyExams.mockResolvedValue([])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    expect(await screen.findByText('No tiene exámenes por controlar')).toBeVisible()
  })
})