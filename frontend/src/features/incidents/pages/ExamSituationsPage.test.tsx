import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { ExamSituationsPage } from './ExamSituationsPage'
import type { SituationWorkspace } from '../types/exam-situation.types'

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
const emptyHistory = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

function setup(options: { state?: 'EN_CURSO' | 'FINALIZADO'; assistant?: boolean; denied?: boolean } = {}) {
  let context = structuredClone(base)
  if (options.state) context.examen.estado = options.state
  if (options.assistant) context.ambiente_control = 1
  let freshState: 'EN_CURSO' | 'FINALIZADO' = context.examen.estado
  let historyCalls = 0
  const api = mockApiWith((request) => {
    const url = new URL(request.url)
    if (url.pathname.endsWith('/situaciones/examenes')) return { body: {
      data: [context.examen], meta: { current_page: 1, last_page: 1, total: 1 },
    } }
    if (url.pathname.endsWith('/contexto')) {
      if (options.denied) return { status: 403, body: { message: 'No está habilitado para este examen.' } }
      const result = structuredClone(context)
      if (url.searchParams.has('id_estudiante')) {
        result.hora_servidor = '2026-10-29T15:05:00Z'
        result.examen.estado = freshState
      }
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
  return { ...api, setFreshState: (state: typeof freshState) => { freshState = state },
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

  it('un examen finalizado permite consultar sin ofrecer registro', async () => {
    setup({ state: 'FINALIZADO' })
    const user = userEvent.setup()
    await selectExam(user)
    expect(await screen.findByText(/Examen finalizado\. Puede consultar/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar situación' })).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Historial de situaciones' })).toBeInTheDocument()
  })

  it('comprueba el cierre antes de abrir, sin enviar un POST', async () => {
    const api = setup()
    const user = userEvent.setup()
    await selectExam(user)
    await screen.findByText('José Pérez')
    api.setFreshState('FINALIZADO')
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText(/El examen o la asistencia cambió/)).toBeInTheDocument()
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