import type { ReactNode } from 'react'
import { act, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ExamInProgressPage, type ExamInProgress, type ExamInProgressExtensions } from '@/features/exams'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { InProgressSituationAction, InProgressSituationsPanel } from './InProgressSituations'
import type { ExamSituation } from '../types/exam-situation.types'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))

const extensions: ExamInProgressExtensions = {
  studentActions: (student, exam) => <InProgressSituationAction student={student} exam={exam} />,
  panels: (exam) => <InProgressSituationsPanel exam={exam} />,
}
const fixture: ExamInProgress = {
  id_examen: 7, nombre_examen: 'Parcial', materia: 'Programación', estado: 'EN_CURSO',
  rol_controlador: 'DOCENTE', id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 100 }, { id_ambiente: 6, nro_aula: '691B', capacidad: 100 }],
  inicio_real_en: '2026-10-30T12:00:00Z', fin_real_en: null,
  inicio_automatico: false, fin_automatico: false,
  fin_programado_en: '2026-10-30T14:00:00Z', hora_servidor_en: '2026-10-30T12:30:00Z',
  totales: { a_tiempo: 1, con_retraso: 0, ausentes: 1, presentes: 1, total: 2, incidencias_pendientes: 0 },
  situaciones_habilitadas: true, total_situaciones: 0,
  estudiantes: [
    { id_estudiante: 19, cod_sis: '202309201', ci: '1234567', nombre_completo: 'Carlos Rocha',
      estado_ingreso: 'INGRESO', hora_ingreso: '08:00', id_ambiente: 5, ambiente: '691A',
      situaciones: { identidad_observada: false, ultimo_cambio_ambiente: null } },
    { id_estudiante: 20, cod_sis: '202309202', ci: '1234568', nombre_completo: 'Ana Ausente',
      estado_ingreso: 'NO_INGRESO', hora_ingreso: null, id_ambiente: 5, ambiente: '691A' },
  ],
}
let exam: ExamInProgress
let history: ExamSituation[]
let contextStatus: number
let postStatus: number

beforeEach(() => {
  // Solo los intervalos: las esperas de Testing Library y userEvent permanecen reales.
  vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
  exam = structuredClone(fixture)
  history = []
  contextStatus = 200
  postStatus = 201
})
afterEach(() => vi.useRealTimers())

function setup() {
  const requests = mockApiWith(({ url, method, body }) => {
    if (url.endsWith('/examenes/7/en-curso')) return { body: { data: structuredClone(exam) } }
    if (url.includes('/situaciones/examenes/7/contexto')) {
      if (contextStatus !== 200) return { status: contextStatus, body: { message: 'Sin permiso para esa fila.' } }
      return { body: { data: {
        examen: { id_examen: 7, nombre_examen: 'Parcial', fecha: '2026-10-30', estado: exam.estado },
        ambiente_control: exam.id_ambiente_asignado, ambientes: exam.ambientes,
        hora_servidor: '2026-10-30T12:31:00Z',
        estudiantes: exam.estudiantes.filter((s) => s.id_estudiante === 19 && s.estado_ingreso !== 'NO_INGRESO')
          .map((s) => ({ ...s, ambiente: { id_ambiente: s.id_ambiente, nro_aula: s.ambiente } })),
        paginacion: { current_page: 1, last_page: 1, total: 1 },
      } } }
    }
    if (url.includes('/examenes/7/situaciones?')) {
      return { body: { data: history, meta: { current_page: 1, last_page: 1, total: history.length } } }
    }
    if (method === 'POST' && url.endsWith('/estudiantes/19/situaciones')) {
      if (postStatus !== 201) return { status: postStatus, body: { message: 'El examen ya finalizó.' } }
      const input = body as { tipo: 'CAMBIO_AMBIENTE' | 'IDENTIFICACION'; motivo: string; id_ambiente_destino?: number }
      const student = exam.estudiantes[0]
      const origin = exam.ambientes.find((r) => r.id_ambiente === student.id_ambiente)!
      const destination = input.id_ambiente_destino
        ? exam.ambientes.find((r) => r.id_ambiente === input.id_ambiente_destino)! : null
      const record: ExamSituation = {
        id_situacion: history.length + 1, tipo: input.tipo, motivo: input.motivo,
        registrado_en: '2026-10-30T12:31:00Z', estudiante: student,
        ambiente_origen: origin, ambiente_destino: destination,
        registrante: { id_usuario: 'docente', nombre_completo: 'Docente Prueba' },
      }
      history = [record, ...history]
      exam.total_situaciones = history.length
      if (destination) {
        student.id_ambiente = destination.id_ambiente
        student.ambiente = destination.nro_aula
        student.situaciones = { identidad_observada: student.situaciones?.identidad_observada ?? false,
          ultimo_cambio_ambiente: { id_situacion: record.id_situacion, registrado_en: record.registrado_en,
            ambiente_origen: origin, ambiente_destino: destination } }
      } else {
        student.situaciones = { identidad_observada: true, ultimo_cambio_ambiente: student.situaciones?.ultimo_cambio_ambiente ?? null }
      }
      return { status: 201, body: { data: record, mensaje: 'Situación registrada' } }
    }
  })
  renderWithRouter(<ExamInProgressPage extensions={extensions} />, {
    route: '/examenes/7/en-curso', path: '/examenes/:examId/en-curso',
  })
  return { ...requests, user: userEvent.setup() }
}
async function poll() {
  await act(async () => { await vi.advanceTimersByTimeAsync(2000) })
}
async function open(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Registrar situación' }))
  return screen.findByRole('dialog', { name: 'Registrar situación excepcional' })
}
async function fillTransfer(user: ReturnType<typeof userEvent.setup>, dialog: HTMLElement) {
  await user.click(within(dialog).getByRole('combobox', { name: 'Ambiente de destino' }))
  await user.click(screen.getByRole('option', { name: '691B' }))
  await user.type(within(dialog).getByLabelText('Motivo'), 'Ruido')
}

describe('HU-057 integrada en la página real de HU-038-A', () => {
  it('abre con contexto fresco y actualiza ambiente, marcas e historial usando el polling existente', async () => {
    const { user, calls } = setup()
    const dialog = await open(user)
    expect(within(dialog).getByText('08:31')).toBeInTheDocument()
    await fillTransfer(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Registrar situación' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    await poll()
    const row = screen.getByRole('row', { name: /202309201/ })
    expect(within(row).getByText('691B')).toBeInTheDocument()
    expect(within(row).getByText('08:00')).toBeInTheDocument()
    expect(within(row).getByText(/Cambio desde 691A/)).toBeInTheDocument()
    expect(await screen.findByText('Situaciones · 1')).toBeInTheDocument()
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(1)
    expect(calls.find((c) => c.method === 'POST')?.body).toEqual({
      tipo: 'CAMBIO_AMBIENTE', motivo: 'Ruido', id_ambiente_destino: 6,
    })
    // Ningún POST de reporte ni de Central de Riesgo.
    expect(calls.some((c) => c.url.includes('/reporte'))).toBe(false)
  })

  it('conserva ambas marcas y actualiza el historial si otro dispositivo añade otra identificación', async () => {
    const { user } = setup()
    const dialog = await open(user)
    await fillTransfer(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Registrar situación' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    await poll()
    exam.estudiantes[0].situaciones!.identidad_observada = true
    history = [{ ...history[0], id_situacion: 2, tipo: 'IDENTIFICACION', ambiente_destino: null,
      motivo: 'Identidad observada desde otro dispositivo.' }, ...history]
    exam.total_situaciones = 2
    await poll()
    expect(await screen.findByText('Situaciones · 2')).toBeInTheDocument()
    const row = screen.getByRole('row', { name: /202309201/ })
    expect(within(row).getByText('Identidad observada')).toBeInTheDocument()
    expect(within(row).getByText(/Cambio desde 691A/)).toBeInTheDocument()
    history = [{ ...history[0], id_situacion: 3, motivo: 'Segunda observación del documento.' }, ...history]
    exam.total_situaciones = 3
    await poll()
    expect(await screen.findByText('Situaciones · 3')).toBeInTheDocument()
  })

  it('auxiliar pierde la fila trasladada pero conserva el historial del aula de origen', async () => {
    exam.rol_controlador = 'AUXILIAR'
    exam.id_ambiente_asignado = 5
    const { user, calls } = setup()
    const dialog = await open(user)
    await fillTransfer(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Registrar situación' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    exam.estudiantes = exam.estudiantes.filter((student) => student.id_ambiente === 5)
    await poll()
    expect(screen.queryByRole('row', { name: /202309201/ })).not.toBeInTheDocument()
    expect(await screen.findByText('Situaciones · 1')).toBeInTheDocument()
    const historyRequests = calls.filter((c) => c.url.includes('/examenes/7/situaciones?'))
    expect(historyRequests.length).toBeGreaterThan(0)
    expect(historyRequests.every((c) => c.url.includes('id_ambiente=5'))).toBe(true)
  })

  it('no ofrece registrar a ausentes y cancelar no escribe', async () => {
    const { user, calls } = setup()
    await user.click(await screen.findByRole('button', { name: 'Todos (2)' }))
    expect(within(screen.getByRole('row', { name: /Ana Ausente/ })).queryByRole('button')).not.toBeInTheDocument()
    const dialog = await open(user)
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0)
  })

  it('bloquea el formulario abierto cuando el polling informa el cierre', async () => {
    const { user, calls } = setup()
    const dialog = await open(user)
    exam.estado = 'FINALIZADO'
    await poll()
    expect(within(dialog).getByRole('button', { name: 'Registrar situación' })).toBeDisabled()
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0)
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByRole('button', { name: 'Registrar situación' })).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Situaciones del examen' })).toBeInTheDocument()
  })

  it('muestra el 409 del POST si el cierre ocurre antes de que lo detecte el polling', async () => {
    postStatus = 409
    const { user, calls } = setup()
    const dialog = await open(user)
    await fillTransfer(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText(/El examen ya finalizó/)).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Registrar situación' })).toBeDisabled()
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(1)
    expect(screen.queryByText('Situación registrada')).not.toBeInTheDocument()
  })

  it('no abre el modal si el contexto ya no autoriza al controlador', async () => {
    contextStatus = 403
    const { user, calls } = setup()
    await user.click(await screen.findByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText('Sin permiso para esa fila.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0)
  })
})