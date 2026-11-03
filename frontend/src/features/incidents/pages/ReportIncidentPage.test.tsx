import type { ReactNode } from 'react'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it } from 'vitest'
import { vi } from 'vitest'
import type { ExamInProgress } from '@/features/exams'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import type { SituationExam, SituationWorkspace } from '../types/exam-situation.types'
import { ReportIncidentPage } from './ReportIncidentPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))

const FINISHED_MESSAGE = 'Solo se pueden reportar incidencias con el examen en curso.'
const exam7: SituationExam = { id_examen: 7, nombre_examen: 'Parcial', fecha: '2026-10-30', estado: 'EN_CURSO' }
const exam8: SituationExam = { id_examen: 8, nombre_examen: 'Final', fecha: '2026-10-31', estado: 'EN_CURSO' }

const workspace: SituationWorkspace = {
  examen: exam7,
  ambiente_control: null,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A' }],
  hora_servidor: '2026-10-30T12:30:00Z',
  estudiantes: [{
    id_estudiante: 19, cod_sis: '202309201', nombre_completo: 'Carlos Rocha', estado_ingreso: 'INGRESO',
    ambiente: { id_ambiente: 5, nro_aula: '691A' },
    situaciones: { identidad_observada: false, ultimo_cambio_ambiente: null },
    total_incidencias: 0,
  }],
  paginacion: { current_page: 1, last_page: 1, total: 1 },
}

const inProgress: ExamInProgress = {
  id_examen: 7, nombre_examen: 'Parcial', materia: 'Programación', estado: 'EN_CURSO',
  rol_controlador: 'DOCENTE', id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 100 }],
  inicio_real_en: '2026-10-30T12:00:00Z', fin_real_en: null, inicio_automatico: false, fin_automatico: false,
  fin_programado_en: '2026-10-30T14:00:00Z', hora_servidor_en: '2026-10-30T12:31:00Z',
  totales: { a_tiempo: 1, con_retraso: 0, ausentes: 0, presentes: 1, total: 1, incidencias_pendientes: 0, expulsados: 0 },
  incidencias_habilitadas: true,
  estudiantes: [{
    id_estudiante: 19, cod_sis: '202309201', ci: '7654321', nombre_completo: 'Carlos Rocha',
    estado_ingreso: 'INGRESO', hora_ingreso: '08:00', id_ambiente: 5, ambiente: '691A',
    expulsado_en: null, total_incidencias: 0,
  }],
}

let exams: SituationExam[]
let context: SituationWorkspace
let contextStatus: number

beforeEach(() => {
  exams = [exam7]
  context = structuredClone(workspace)
  contextStatus = 200
})

function setup() {
  const api = mockApiWith(({ url, method, body }) => {
    const path = new URL(url).pathname
    if (path.endsWith('/incidencias/examenes')) {
      return { body: { data: exams, meta: { current_page: 1, last_page: 1, total: exams.length } } }
    }
    if (path.endsWith('/incidencias/examenes/7/contexto')) {
      if (contextStatus !== 200) return { status: contextStatus, body: { message: FINISHED_MESSAGE } }
      return { body: { data: structuredClone(context) } }
    }
    if (path.endsWith('/examenes/7/en-curso')) return { body: { data: structuredClone(inProgress) } }
    if (path.endsWith('/tipos-falta')) {
      return { body: { data: [{ id_falta: 83, nombre: 'Material no permitido', descripcion: 'Dispositivos.' }] } }
    }
    if (method === 'POST' && path.endsWith('/examenes/7/estudiantes/19/incidencias')) {
      void body
      context.estudiantes[0].total_incidencias = (context.estudiantes[0].total_incidencias ?? 0) + 1
      return { status: 201, body: { data: { id_reporte_est: 1, id_intento: null, estado: 'PENDIENTE' } } }
    }
    throw new Error(`Petición no esperada: ${method} ${url}`)
  })
  renderWithRouter(<ReportIncidentPage />, { route: '/reportar-incidencia', path: '/reportar-incidencia' })
  return { ...api, user: userEvent.setup({ applyAccept: false }) }
}

async function openForm(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Reportar' }))
  return screen.findByRole('dialog', { name: 'Reportar incidencia' })
}

async function submit(user: ReturnType<typeof userEvent.setup>, dialog: HTMLElement) {
  await waitFor(() => expect(within(dialog).getByRole('combobox')).toBeEnabled())
  await user.click(within(dialog).getByRole('combobox'))
  await user.click(screen.getByRole('option', { name: 'Material no permitido' }))
  await user.type(within(dialog).getByRole('textbox', { name: 'Descripción' }), 'Usó un celular no autorizado.')
  await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
  await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
}

describe('Reportar incidencia desde el menú (HU-042)', () => {
  it('sin exámenes En curso muestra el estado vacío propio', async () => {
    exams = []
    setup()
    expect(await screen.findByText('No tiene exámenes en curso para reportar incidencias')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reportar' })).not.toBeInTheDocument()
  })

  it('con un solo examen lo selecciona y abre el formulario precargado', async () => {
    const { user } = setup()
    const dialog = await openForm(user)
    expect(within(dialog).getByText('Carlos Rocha', { exact: false })).toBeInTheDocument()
    expect(within(dialog).getByText('Parcial')).toBeInTheDocument()
    expect(within(dialog).getByText(/691A/)).toBeInTheDocument()
    expect(within(dialog).getByText(/08:31/)).toBeInTheDocument()
  })

  it('con varios exámenes pide elegir antes de listar estudiantes', async () => {
    exams = [exam7, exam8]
    const { user } = setup()
    await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
    expect(screen.queryByRole('button', { name: 'Reportar' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('option', { name: /Parcial/ }))
    expect(await screen.findByText('Carlos Rocha')).toBeInTheDocument()
  })

  it('busca por SIS, nombre o CI en el servidor', async () => {
    const { user, calls } = setup()
    await screen.findByText('Carlos Rocha')
    expect(screen.getByLabelText('Nombre, código SIS o CI')).toBeInTheDocument()
    await user.type(screen.getByLabelText('Nombre, código SIS o CI'), '7654321')
    await user.click(screen.getByRole('button', { name: 'Buscar' }))
    await waitFor(() => expect(calls.some((call) => call.url.includes('q=7654321'))).toBe(true))
  })

  it('envía el reporte, muestra el aviso y cuenta «2 incidencias» sin tocar rutas de HU-057', async () => {
    const { user, calls } = setup()
    for (let i = 0; i < 2; i++) {
      const dialog = await openForm(user)
      await submit(user, dialog)
      expect(await screen.findByText('Enviado a revisión del docente')).toBeInTheDocument()
    }
    expect(await screen.findByText('2 incidencias')).toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(2)
    expect(calls.some((call) => call.url.includes('/situaciones'))).toBe(false)
  })

  it('cancelar no crea nada', async () => {
    const { user, calls } = setup()
    const dialog = await openForm(user)
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('si el examen ya no está En curso muestra el motivo y no ofrece reportar', async () => {
    contextStatus = 409
    setup()
    expect(await screen.findByText(FINISHED_MESSAGE)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reportar' })).not.toBeInTheDocument()
  })
})
