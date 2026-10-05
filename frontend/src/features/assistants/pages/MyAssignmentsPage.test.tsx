import { screen, within } from '@testing-library/react'
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

  it('muestra el estado de cada examen vigente', async () => {
    listMyExams.mockResolvedValue([
      assignedExam,
      { ...assignedExam, id_examen: 9, estado: 'EN_INGRESO' },
      { ...assignedExam, id_examen: 10, estado: 'EN_CURSO' },
    ])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    // El sidebar también tiene un ítem «En curso»: se mira dentro de cada tarjeta.
    await screen.findAllByText('1er Parcial BD I')
    const [programado, enIngreso, enCurso] = screen.getAllByRole('article')
    expect(within(programado).getByText('Programado')).toBeVisible()
    expect(within(enIngreso).getByText('Control de ingreso abierto')).toBeVisible()
    expect(within(enCurso).getByText('En curso')).toBeVisible()
  })

  it('con el ingreso abierto la tarjeta lleva al control de ingreso de ese examen', async () => {
    listMyExams.mockResolvedValue([{ ...assignedExam, id_examen: 9, estado: 'EN_INGRESO' }])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    await screen.findAllByText('1er Parcial BD I')
    const [card] = screen.getAllByRole('article')
    expect(within(card).getByRole('link', { name: /controlar ingreso/i })).toHaveAttribute(
      'href',
      '/examenes/9/control-ingreso'
    )
  })

  it('no ofrece controlar el ingreso mientras el examen está programado o en curso', async () => {
    listMyExams.mockResolvedValue([
      assignedExam,
      { ...assignedExam, id_examen: 10, estado: 'EN_CURSO' },
    ])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    await screen.findAllByText('1er Parcial BD I')
    expect(screen.queryByRole('link', { name: /controlar ingreso/i })).not.toBeInTheDocument()
  })

  it('un examen de un solo ambiente sin asignación explícita muestra ese ambiente', async () => {
    listMyExams.mockResolvedValue([{ ...assignedExam, ambiente_por_defecto: true }])

    renderWithRouter(<MyAssignmentsPage />, { route: '/mis-examenes' })

    expect(await screen.findByText('Auditorio FCyT')).toBeVisible()
    expect(screen.getByText(/el único ambiente de este examen/)).toBeVisible()
    expect(screen.queryByText(/asignado por el docente/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Aún sin ambiente asignado/)).not.toBeInTheDocument()
  })
})
