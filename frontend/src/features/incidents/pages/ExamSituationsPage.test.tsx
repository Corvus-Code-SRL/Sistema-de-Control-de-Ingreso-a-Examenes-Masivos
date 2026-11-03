import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { ExamSituationsPage } from './ExamSituationsPage'
import type { SituationExam, SituationWorkspace } from '../types/exam-situation.types'

const base: SituationWorkspace = {
  examen: { id_examen: 10, nombre_examen: 'Primer parcial', fecha: '2026-10-29', estado: 'EN_CURSO' },
  ambiente_control: null,
  ambientes: [{ id_ambiente: 1, nro_aula: '691A' }, { id_ambiente: 2, nro_aula: '692B' }],
  hora_servidor: '2026-10-29T15:00:00Z',
  estudiantes: [{
    id_estudiante: 20, cod_sis: '202100001', nombre_completo: 'José Pérez', estado_ingreso: 'CON_RETRASO',
    ambiente: { id_ambiente: 1, nro_aula: '691A' },
    situaciones: { identidad_observada: false, ultimo_cambio_ambiente: null },
  }],
  paginacion: { current_page: 1, last_page: 1, total: 1 },
}
const CLOSED_MESSAGE = 'Solo se pueden registrar situaciones con el ingreso abierto o con el examen en curso.'
const emptyHistory = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

type ExamState = 'EN_INGRESO' | 'EN_CURSO'

function setup(options: {
  state?: ExamState; assistant?: boolean; denied?: boolean; noExams?: boolean; extraExams?: SituationExam[]
} = {}) {
  let context = structuredClone(base)
  if (options.state) context.examen.estado = options.state
  if (options.assistant) context.ambiente_control = 1
  let closedByServer = false
  let historyCalls = 0
  const api = mockApiWith((request) => {
    const url = new URL(request.url)
    if (url.pathname.endsWith('/situaciones/examenes')) {
      const data = options.noExams ? [] : [context.examen, ...(options.extraExams ?? [])]
      return { body: { data, meta: { current_page: 1, last_page: 1, total: data.length } } }
    }
    if (url.pathname.endsWith('/contexto')) {
      if (options.denied) return { status: 403, body: { message: 'No está habilitado para este examen.' } }
      if (closedByServer && url.searchParams.has('id_estudiante')) {
        return { status: 409, body: { message: CLOSED_MESSAGE } }
      }
      const result = structuredClone(context)
      if (url.searchParams.has('id_estudiante')) result.hora_servidor = '2026-10-29T15:05:00Z'
      return { body: { data: result } }
    }
    if (url.pathname.endsWith('/estudiantes/20/situaciones') && request.method === 'POST') {
      const body = request.body as { tipo: string }
      if (body.tipo === 'CAMBIO_AMBIENTE') {
        context.estudiantes[0].ambiente = { id_ambiente: 2, nro_aula: '692B' }
        context.estudiantes[0].situaciones.ultimo_cambio_ambiente = {
          id_situacion: 100, registrado_en: '2026-10-29T15:06:00Z',
          ambiente_origen: { id_ambiente: 1, nro_aula: '691A' },
          ambiente_destino: { id_ambiente: 2, nro_aula: '692B' },
        }
        if (options.assistant) {
          context.estudiantes = []
          context.paginacion.total = 0
        }
      } else context.estudiantes[0].situaciones.identidad_observada = true
      return { status: 201, body: { data: { id_situacion: 100 }, mensaje: 'Situación registrada' } }
    }
    if (url.pathname.endsWith('/examenes/10/situaciones')) {
      historyCalls += 1
      return { body: emptyHistory }
    }
    // AppShell puede consultar el contador compartido.
    if (url.pathname.includes('/incidencias')) return { body: { data: [], meta: { total_pendientes: 0 } } }
  })
  renderWithRouter(<ExamSituationsPage />)
  return { ...api, closeExam: () => { closedByServer = true },
    getHistoryCalls: () => historyCalls, setContext: (value: SituationWorkspace) => { context = value } }
}

async function selectExam(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
  await user.click(await screen.findByRole('option', { name: /Primer parcial/ }))
}

async function submitMove(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Registrar situación' }))
  const modal = await screen.findByRole('dialog')
  await user.click(within(modal).getByRole('combobox', { name: 'Ambiente de destino' }))
  await user.click(screen.getByRole('option', { name: '692B' }))
  await user.type(within(modal).getByLabelText('Motivo'), 'Ruido')
  await user.click(within(modal).getByRole('button', { name: 'Registrar situación' }))
}

afterEach(() => { vi.unstubAllGlobals() })

describe('Acceso independiente HU-057', () => {
  it('selecciona examen y refresca la hora antes de abrir el modal; cancelar no escribe', async () => {
    const api = setup()
    const user = userEvent.setup()
    await selectExam(user)
    expect(await screen.findByText('Presente con retraso')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    const modal = await screen.findByRole('dialog')
    expect(within(modal).getByText('11:05')).toBeInTheDocument()
    expect(api.calls.some((call) => call.url.includes('id_estudiante=20'))).toBe(true)
    await user.click(within(modal).getByRole('button', { name: 'Cancelar' }))
    expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('anuncia la búsqueda por nombre, código SIS o CI y envía la CI como criterio', async () => {
    const api = setup()
    const user = userEvent.setup()
    await selectExam(user)
    const search = await screen.findByLabelText('Nombre, código SIS o CI')
    await user.type(search, '7654321')
    await user.click(screen.getByRole('button', { name: 'Buscar' }))
    await waitFor(() => {
      expect(api.calls.some((call) => call.url.includes('/contexto') && call.url.includes('q=7654321'))).toBe(true)
    })
  })

  it('traslada y recarga ambiente, marca e historial sin perder el retraso', async () => {
    const api = setup()
    const user = userEvent.setup()
    await selectExam(user)
    await screen.findByText('José Pérez')
    await waitFor(() => expect(api.getHistoryCalls()).toBeGreaterThan(0))
    const before = api.getHistoryCalls()
    await submitMove(user)
    expect(await screen.findByText('202100001 · Ambiente 692B')).toBeInTheDocument()
    expect(screen.getByText(/Cambio desde 691A/)).toBeInTheDocument()
    expect(screen.getByText('Presente con retraso')).toBeInTheDocument()
    await waitFor(() => expect(api.getHistoryCalls()).toBeGreaterThan(before))
    expect(api.calls.find((call) => call.method === 'POST')?.body).toEqual({
      tipo: 'CAMBIO_AMBIENTE', motivo: 'Ruido', id_ambiente_destino: 2,
    })
  })

  it('la identificación conserva la marca de un cambio anterior', async () => {
    const api = setup()
    const context = structuredClone(base)
    context.estudiantes[0].situaciones.ultimo_cambio_ambiente = {
      id_situacion: 90, registrado_en: base.hora_servidor,
      ambiente_origen: { id_ambiente: 2, nro_aula: '692B' }, ambiente_destino: base.ambientes[0],
    }
    api.setContext(context)
    const user = userEvent.setup()
    await selectExam(user)
    await user.click(await screen.findByRole('button', { name: 'Registrar situación' }))
    const modal = await screen.findByRole('dialog')
    await user.click(within(modal).getByRole('combobox', { name: 'Tipo de situación' }))
    await user.click(screen.getByRole('option', { name: 'Problema de identificación' }))
    await user.type(within(modal).getByLabelText('Descripción'), 'Documento deteriorado')
    await user.click(within(modal).getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText('Identidad observada')).toBeInTheDocument()
    expect(screen.getByText(/Cambio desde 692B/)).toBeInTheDocument()
    expect(screen.getByText('Presente con retraso')).toBeInTheDocument()
    expect(api.calls.find((call) => call.method === 'POST')?.body).toEqual({
      tipo: 'IDENTIFICACION', motivo: 'Documento deteriorado',
    })
  })

  it('el auxiliar tiene su filtro bloqueado y pierde la fila trasladada', async () => {
    setup({ assistant: true })
    const user = userEvent.setup()
    await selectExam(user)
    expect(await screen.findByRole('combobox', { name: 'Ambiente' })).toBeDisabled()
    await submitMove(user)
    expect(await screen.findByText('No hay estudiantes presentes para este filtro.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar situación' })).not.toBeInTheDocument()
  })

  it('sin exámenes con ingreso abierto ni en curso muestra el estado vacío de HU-057', async () => {
    setup({ noExams: true })
    expect(await screen.findByText('No tiene exámenes con ingreso abierto ni en curso')).toBeInTheDocument()
    expect(screen.getByText(
      'Las situaciones excepcionales se registran desde que se abre el control de ingreso hasta que termina el examen.'
    )).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Estado del examen' })).not.toBeInTheDocument()
  })

  it('el selector lista exámenes en ingreso y en curso con su estado, sin opción de finalizados', async () => {
    setup({
      state: 'EN_INGRESO',
      extraExams: [{ id_examen: 11, nombre_examen: 'Segundo parcial', fecha: '2026-10-30', estado: 'EN_CURSO' }],
    })
    const user = userEvent.setup()
    await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
    expect(await screen.findByRole('option', { name: /Primer parcial · 2026-10-29 · En ingreso/ })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Segundo parcial · 2026-10-30 · En curso/ })).toBeInTheDocument()
    expect(screen.queryByText(/Finalizados/)).not.toBeInTheDocument()
  })

  it('durante el ingreso permite buscar y registrar un cambio de ambiente', async () => {
    const api = setup({ state: 'EN_INGRESO' })
    const user = userEvent.setup()
    await selectExam(user)
    expect(await screen.findByText('José Pérez')).toBeInTheDocument()
    await submitMove(user)
    expect(await screen.findByText('202100001 · Ambiente 692B')).toBeInTheDocument()
    expect(api.calls.find((call) => call.method === 'POST')?.body).toEqual({
      tipo: 'CAMBIO_AMBIENTE', motivo: 'Ruido', id_ambiente_destino: 2,
    })
  })

  it('comprueba el estado antes de abrir: si el servidor lo rechaza no abre ni envía un POST', async () => {
    const api = setup()
    const user = userEvent.setup()
    await selectExam(user)
    await screen.findByText('José Pérez')
    api.closeExam()
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText(CLOSED_MESSAGE)).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('un rechazo de autorización no muestra datos ni acciones', async () => {
    setup({ denied: true })
    const user = userEvent.setup()
    await selectExam(user)
    expect(await screen.findByText('No está habilitado para este examen.')).toBeInTheDocument()
    expect(screen.queryByText('José Pérez')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar situación' })).not.toBeInTheDocument()
  })
})