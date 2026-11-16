import type { ReactNode } from 'react'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { renderWithRouter } from '@/test/render'
import { collaboratorsService } from '../services/collaboratorsService'
import type {
  CollaboratorCandidate,
  InvitableExam,
  SentInvitation,
} from '../types/collaborator.types'
import { CollaboratorInvitationsPage } from './CollaboratorInvitationsPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))
vi.mock('../services/collaboratorsService', () => ({
  collaboratorsService: {
    listInvitableExams: vi.fn(),
    searchTeachers: vi.fn(),
    sendInvitation: vi.fn(),
    listSentInvitations: vi.fn(),
  },
}))

const service = vi.mocked(collaboratorsService)

const EXAM: InvitableExam = {
  id_examen: 9811,
  nombre_examen: 'Parcial Cálculo I',
  fecha: '2026-12-10',
  hora_inicio: '08:00',
  hora_fin: '09:30',
  materia: { id_materia: 1, nombre: 'Cálculo I', codigo: 'MAT-101' },
  carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
  ambientes: [{ id_ambiente: 1, nro_aula: '691A' }],
}

const ROLANDO: CollaboratorCandidate = {
  id_usuario: 'u-rolando',
  nombre_completo: 'Rolando Vega Méndez',
  cod_sis: '10602',
  ya_invitado: false,
}

const ELENA: CollaboratorCandidate = {
  id_usuario: 'u-elena',
  nombre_completo: 'Elena Prado Rojas',
  cod_sis: '10601',
  ya_invitado: true,
}

function invitation(id: number, estado: SentInvitation['estado'], name: string): SentInvitation {
  return {
    id_invitacion: id,
    estado,
    fecha_invitacion: '2026-11-13T13:00:00+00:00',
    fecha_respuesta: null,
    fecha_sin_efecto: null,
    permite_agregar_grupos: true,
    examen: {
      id_examen: 9811,
      nombre_examen: 'Parcial Cálculo I',
      materia: 'Cálculo I',
      carrera: 'Ingeniería de Sistemas',
      fecha: '2026-12-10',
      hora_inicio: '08:00',
      hora_fin: '09:30',
      estado: 'PROGRAMADO',
    },
    docente: { id_usuario: `u-${id}`, nombre_completo: name, cod_sis: null },
  }
}

function renderPage() {
  return renderWithRouter(<CollaboratorInvitationsPage />, {
    route: '/colaboradores/invitar',
    path: '/colaboradores/invitar',
  })
}

async function chooseExam(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
  await user.click(await screen.findByRole('option', { name: /Parcial Cálculo I/ }))
}

async function searchFor(user: ReturnType<typeof userEvent.setup>, term: string) {
  await user.type(screen.getByLabelText('Docente'), term)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

beforeEach(() => {
  vi.clearAllMocks()
  service.listInvitableExams.mockResolvedValue([EXAM])
  service.listSentInvitations.mockResolvedValue([])
})

describe('HU-026: invitar docentes colaboradores', () => {
  it('el desplegable muestra nombre, materia, fecha, hora y ambientes del examen', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('combobox', { name: 'Examen' }))

    expect(
      await screen.findByRole('option', {
        name: 'Parcial Cálculo I · Cálculo I · 10/12/2026 · 08:00–09:30 · Aula 691A',
      })
    ).toBeInTheDocument()
  })

  it('sin examen elegido no se puede buscar y se explica por qué', async () => {
    renderPage()

    expect(await screen.findByText(/Elija primero un examen/)).toBeInTheDocument()
    expect(screen.getByLabelText('Docente')).toBeDisabled()
    expect(service.searchTeachers).not.toHaveBeenCalled()
  })

  it('busca en la materia del examen elegido y marca al docente que ya fue invitado', async () => {
    const user = userEvent.setup()
    service.searchTeachers.mockResolvedValue([ROLANDO, ELENA])
    renderPage()

    await chooseExam(user)
    await searchFor(user, 'prado')

    expect(await screen.findByText('Rolando Vega Méndez')).toBeInTheDocument()
    expect(service.searchTeachers).toHaveBeenCalledWith(9811, 'prado', expect.any(AbortSignal))

    const elena = screen.getByText('Elena Prado Rojas').closest('li') as HTMLElement
    expect(within(elena).getByText('Ya invitado')).toBeInTheDocument()
    expect(within(elena).queryByRole('button', { name: /Invitar/ })).not.toBeInTheDocument()
  })

  it('no busca con menos de 2 caracteres', async () => {
    const user = userEvent.setup()
    renderPage()

    await chooseExam(user)
    await user.type(screen.getByLabelText('Docente'), 'a')

    expect(screen.getByRole('button', { name: 'Buscar' })).toBeDisabled()
    expect(service.searchTeachers).not.toHaveBeenCalled()
  })

  it('sin coincidencias muestra el texto de la historia', async () => {
    const user = userEvent.setup()
    service.searchTeachers.mockResolvedValue([])
    renderPage()

    await chooseExam(user)
    await searchFor(user, 'zzz')

    expect(
      await screen.findByText('No hay docentes de esta materia que coincidan con la búsqueda')
    ).toBeInTheDocument()
  })

  it('invitar abre la confirmación con la opción de grupos marcada, y cancelar no envía nada', async () => {
    const user = userEvent.setup()
    service.searchTeachers.mockResolvedValue([ROLANDO])
    renderPage()

    await chooseExam(user)
    await searchFor(user, 'rolando')
    await user.click(await screen.findByRole('button', { name: /Invitar/ }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('Rolando Vega Méndez')).toBeInTheDocument()
    expect(within(dialog).getByText('Parcial Cálculo I')).toBeInTheDocument()
    expect(
      within(dialog).getByRole('checkbox', { name: 'Permitir que agregue sus grupos al examen' })
    ).toBeChecked()

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(service.sendInvitation).not.toHaveBeenCalled()
  })

  it('al confirmar envía la invitación con la opción elegida, avisa y recarga las enviadas', async () => {
    const user = userEvent.setup()
    service.searchTeachers.mockResolvedValue([ROLANDO])
    service.sendInvitation.mockResolvedValue(invitation(1, 'PENDIENTE', ROLANDO.nombre_completo))
    renderPage()

    await chooseExam(user)
    await searchFor(user, 'rolando')
    await user.click(await screen.findByRole('button', { name: /Invitar/ }))

    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('checkbox'))
    await user.click(within(dialog).getByRole('button', { name: 'Enviar invitación' }))

    await waitFor(() =>
      expect(service.sendInvitation).toHaveBeenCalledWith(9811, {
        id_docente: 'u-rolando',
        permite_agregar_grupos: false,
      })
    )
    expect(await screen.findByText('Invitación enviada a Rolando Vega Méndez.')).toBeInTheDocument()
    await waitFor(() => expect(service.listSentInvitations).toHaveBeenCalledTimes(2))

    const row = screen.getByText('Rolando Vega Méndez').closest('li') as HTMLElement
    expect(within(row).getByText('Ya invitado')).toBeInTheDocument()
  })

  it('un rechazo del servidor (409) se muestra en la confirmación y no la cierra', async () => {
    const user = userEvent.setup()
    service.searchTeachers.mockResolvedValue([ROLANDO])
    service.sendInvitation.mockRejectedValue(
      new ApiError(409, 'El docente ya tiene una invitación pendiente o aceptada para este examen.')
    )
    renderPage()

    await chooseExam(user)
    await searchFor(user, 'rolando')
    await user.click(await screen.findByRole('button', { name: /Invitar/ }))
    await user.click(await screen.findByRole('button', { name: 'Enviar invitación' }))

    expect(
      await screen.findByText('El docente ya tiene una invitación pendiente o aceptada para este examen.')
    ).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('lista las invitaciones enviadas con sus cuatro estados', async () => {
    service.listSentInvitations.mockResolvedValue([
      invitation(4, 'SIN_EFECTO', 'Carmen Ríos'),
      invitation(3, 'RECHAZADA', 'Rolando Vega'),
      invitation(2, 'ACEPTADA', 'Elena Prado'),
      invitation(1, 'PENDIENTE', 'Rosario Salazar'),
    ])
    renderPage()

    const table = await screen.findByRole('table', { name: 'Invitaciones enviadas' })
    const rows = within(table).getAllByRole('row').slice(1)

    expect(rows).toHaveLength(4)
    expect(within(rows[0]).getByText('Carmen Ríos')).toBeInTheDocument()
    for (const label of ['Sin efecto', 'Rechazada', 'Aceptada', 'Pendiente']) {
      expect(within(table).getByText(label)).toBeInTheDocument()
    }
    expect(within(rows[3]).getByText('Pendiente')).toBeInTheDocument()
  })

  it('sin exámenes programados y sin invitaciones muestra cada estado vacío', async () => {
    service.listInvitableExams.mockResolvedValue([])
    renderPage()

    expect(await screen.findByText('No tiene exámenes programados')).toBeInTheDocument()
    expect(await screen.findByText('Aún no ha enviado invitaciones')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
  })

  it('un 403 muestra el aviso de falta de permiso en lugar del contenido', async () => {
    service.listInvitableExams.mockRejectedValue(
      new ApiError(403, 'Solo un docente puede invitar colaboradores a un examen.')
    )
    service.listSentInvitations.mockRejectedValue(
      new ApiError(403, 'Solo un docente puede invitar colaboradores a un examen.')
    )
    renderPage()

    expect(await screen.findByText('Sin permiso')).toBeInTheDocument()
    expect(screen.queryByText('Invitaciones enviadas')).not.toBeInTheDocument()
  })

  it('un fallo al cargar las enviadas permite reintentar', async () => {
    const user = userEvent.setup()
    service.listSentInvitations.mockRejectedValueOnce(new ApiError(500, 'Error del servidor'))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    await waitFor(() => expect(service.listSentInvitations).toHaveBeenCalledTimes(2))
  })
})
