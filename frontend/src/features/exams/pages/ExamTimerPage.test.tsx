import type { ReactNode } from 'react'
import { act, fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { ExamTimerCard } from '../components/ExamTimerCard'
import { ExamTimersProvider } from '../hooks/useExamTimers'
import { examTimerService, type ExamTimer, type ExamTimerDetail } from '../services/examTimerService'
import { ExamTimerPage } from './ExamTimerPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))

const initialTime = '2026-11-17T18:20:00Z'
const notStarted: ExamTimer = {
  id_examen: 7, nombre_examen: 'Primer Parcial', estado: 'EN_CURSO', fecha: '2026-11-17',
  hora_inicio: '14:00', hora_fin: '16:00', duracion: 120, fin_permitido_en: '2026-11-17T16:00:00-04:00',
  cronometro_iniciado_en: null, cronometro_vence_en: null,
}
const started: ExamTimer = {
  ...notStarted, cronometro_iniciado_en: initialTime, cronometro_vence_en: '2026-11-17T18:40:00Z',
}

function mountPage(route = '/examenes/controlar-tiempo?exam=7', showCard = false) {
  return renderWithRouter(
    <ExamTimersProvider><ExamTimerPage />{showCard && <ExamTimerCard />}</ExamTimersProvider>,
    { route },
  )
}

function mockBackend(exams = [notStarted]) {
  const state = {
    exams, detail: notStarted, mutation: started, now: () => initialTime,
    failDetail: false, failPolling: false,
  }
  const { calls } = mockApiWith(({ url, method }) => {
    if (url.endsWith('/examenes/cronometros')) {
      if (state.failPolling) throw new TypeError('Failed to fetch')
      return { body: { data: { examenes: state.exams, hora_servidor_en: state.now() } } }
    }
    if (url.endsWith('/7/cronometro')) {
      if (method === 'GET' && state.failDetail) throw new TypeError('Failed to fetch')
      if (method !== 'GET') state.exams = [state.mutation]
      return { body: { data: { examen: method === 'GET' ? state.detail : state.mutation, hora_servidor_en: state.now() } } }
    }
    return undefined
  })
  return { state, calls }
}

async function advance(ms: number) {
  await act(async () => { await vi.advanceTimersByTimeAsync(ms) })
}

describe('HU-038 — Controlar tiempo del examen', () => {
  afterEach(() => vi.useRealTimers())

  it('solo ofrece exámenes en curso', async () => {
    mockBackend([
      notStarted,
      { ...started, id_examen: 8, estado: 'PROGRAMADO' },
      { ...started, id_examen: 9, estado: 'FINALIZADO' },
      { ...started, id_examen: 10, estado: 'CANCELADO' },
    ])
    mountPage('/examenes/controlar-tiempo')
    const user = userEvent.setup()
    await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
    expect(screen.getAllByRole('option')).toHaveLength(1)
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))
    expect(screen.getByRole('heading', { name: 'Primer Parcial' })).toBeInTheDocument()
    expect(screen.getByRole('timer')).toHaveTextContent('—')
  })

  it('persiste los minutos seleccionados una sola vez pese a clics repetidos', async () => {
    const { calls } = mockBackend()
    let resolve!: (detail: ExamTimerDetail) => void
    vi.spyOn(examTimerService, 'get').mockReturnValueOnce(new Promise((complete) => { resolve = complete }))
    mountPage()
    const start = await screen.findByRole('button', { name: 'Iniciar cronómetro' })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Minutos concedidos' }), { target: { value: '20' } })

    act(() => { fireEvent.click(start); fireEvent.click(start) })
    expect(start).toBeDisabled()
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
    await act(async () => { resolve({ examen: notStarted, hora_servidor_en: initialTime }) })

    expect(calls.filter(({ method }) => method === 'POST').map(({ body }) => body)).toEqual([{ minutos: 20 }])
    expect(screen.getByRole('timer')).toHaveTextContent('00:20:00')
    expect(screen.queryByRole('button', { name: 'Iniciar cronómetro' })).not.toBeInTheDocument()
  })

  it('propone como minutos los que quedan hasta el límite si la duración completa no cabe', async () => {
    mockBackend()
    mountPage()
    // Son las 14:20 y el límite es 16:00: quedan menos de 100 minutos (el reloj avanza unos ms) de los 120 previstos.
    const field = await screen.findByRole('spinbutton', { name: 'Minutos concedidos' })
    await vi.waitFor(() => expect(Number((field as HTMLInputElement).value)).toBeLessThanOrEqual(100))
    expect(Number((field as HTMLInputElement).value)).toBeGreaterThanOrEqual(99)
  })

  it('muestra el 422 de minutos sin ofrecer reducir el tiempo', async () => {
    const { calls } = mockBackend()
    vi.spyOn(examTimerService, 'save').mockRejectedValueOnce(new ApiError(422, 'Validación fallida', {
      minutos: ['El tiempo solicitado excede el límite disponible'],
    }))
    mountPage()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Iniciar cronómetro' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('El tiempo solicitado excede el límite disponible')
    expect(screen.queryByRole('button', { name: 'Iniciar con el tiempo disponible' })).not.toBeInTheDocument()
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it.each(['PROGRAMADO', 'FINALIZADO', 'CANCELADO'] as const)('rechaza un inicio si el detalle vuelve %s', async (estado) => {
    const { state, calls } = mockBackend()
    state.detail = { ...notStarted, estado }
    mountPage()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Iniciar cronómetro' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('El examen ya no está disponible')
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('rechaza el inicio al alcanzar el fin programado', async () => {
    const { state, calls } = mockBackend()
    state.now = () => '2026-11-17T20:00:00Z'
    mountPage()
    expect(await screen.findByRole('button', { name: 'Iniciar cronómetro' })).toBeDisabled()
    expect(screen.getByRole('spinbutton')).toBeDisabled()
    expect(screen.getByText(/La hora final permitida ya pasó/)).toBeInTheDocument()
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('permite reintentar una consulta de inicio que falló', async () => {
    const { state, calls } = mockBackend()
    state.failDetail = true
    mountPage()
    const user = userEvent.setup()
    await user.click(await screen.findByRole('button', { name: 'Iniciar cronómetro' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Intente nuevamente')
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
    state.failDetail = false
    state.detail = started
    state.exams = [started]
    await user.click(screen.getByRole('button', { name: 'Iniciar cronómetro' }))
    expect(screen.getByRole('timer')).toHaveTextContent('00:20:00')
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('recupera la cuenta del servidor en un montaje nuevo y deja visible el tiempo agotado', async () => {
    const { state, calls } = mockBackend()
    state.exams = [started]
    state.now = () => '2026-11-17T18:25:00Z'
    const first = mountPage()
    expect(await screen.findByText('00:15:00')).toBeInTheDocument()
    first.unmount()

    state.now = () => '2026-11-17T18:40:00Z'
    mountPage()
    expect(await screen.findByText('Tiempo agotado')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Iniciar cronómetro' })).not.toBeInTheDocument()
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('guarda el nuevo total con PUT y muestra el vencimiento persistido por el servidor', async () => {
    const { state, calls } = mockBackend()
    state.exams = [started]
    state.now = () => '2026-11-17T18:25:00Z'
    state.mutation = { ...started, cronometro_vence_en: '2026-11-17T18:50:00Z' }
    mountPage()
    const user = userEvent.setup()
    await user.click(await screen.findByRole('button', { name: 'Ajustar tiempo' }))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Duración total en minutos' }), { target: { value: '30' } })
    await user.click(screen.getByRole('button', { name: 'Guardar ajuste' }))
    expect(calls.filter(({ method }) => method === 'PUT').map(({ body }) => body)).toEqual([{ minutos: 30 }])
    expect(screen.getByRole('timer')).toHaveTextContent('00:25:00')
  })

  it('permite ajustar una cuenta agotada antes del límite del examen', async () => {
    const { state, calls } = mockBackend([started])
    state.now = () => '2026-11-17T18:41:00Z'
    state.mutation = { ...started, cronometro_vence_en: '2026-11-17T18:50:00Z' }
    mountPage()
    const user = userEvent.setup()
    expect(await screen.findByText('Tiempo agotado')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Ajustar tiempo' }))
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '30' } })
    await user.click(screen.getByRole('button', { name: 'Guardar ajuste' }))
    expect(calls.filter(({ method }) => method === 'PUT').map(({ body }) => body)).toEqual([{ minutos: 30 }])
    expect(screen.getByRole('timer')).toHaveTextContent('00:09:00')
  })

  it('bloquea un ajuste abierto cuando se alcanza la hora final permitida', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance', 'Date'] })
    const { state, calls } = mockBackend([started])
    state.now = () => '2026-11-17T19:59:59Z'
    mountPage()
    await advance(0)
    fireEvent.click(screen.getByRole('button', { name: 'Ajustar tiempo' }))
    await advance(1_000)
    expect(screen.getByRole('spinbutton')).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Guardar ajuste' })).toBeDisabled()
    expect(screen.getByText(/La hora final permitida ya pasó/)).toBeInTheDocument()
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('avisa a los diez minutos y llega a cero sin consultar por segundo ni escribir', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance', 'Date'] })
    vi.setSystemTime(Date.parse('2026-11-17T18:29:59Z'))
    const { state, calls } = mockBackend()
    state.exams = [started]
    state.now = () => new Date().toISOString()
    mountPage()
    await advance(0)
    expect(screen.getByRole('timer')).toHaveTextContent('00:10:01')
    await advance(1_000)
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 10 minutos o menos.')
    expect(calls).toHaveLength(1)

    await advance(600_000)
    expect(screen.getByRole('timer')).toHaveTextContent('00:00')
    expect(screen.getByRole('status')).toHaveTextContent('Tiempo agotado')
    expect(calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('conserva la cuenta cuando falla el sondeo y la retira al confirmarse el cierre externo', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance', 'Date'] })
    vi.setSystemTime(Date.parse(initialTime))
    const { state } = mockBackend()
    state.exams = [started]
    state.now = () => new Date().toISOString()
    mountPage()
    await advance(0)

    state.failPolling = true
    await advance(15_000)
    expect(screen.getByRole('alert')).toHaveTextContent('Se reintentará automáticamente')
    expect(screen.getByRole('timer')).toHaveTextContent('00:19:45')

    state.failPolling = false
    state.exams = []
    await advance(15_000)
    expect(screen.getByRole('alert')).toHaveTextContent('El examen «Primer Parcial» ya no está disponible')
    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
  })

  it.each([
    ['FINALIZADO', notStarted], ['CANCELADO', notStarted],
    ['FINALIZADO', started], ['CANCELADO', started],
  ] as const)('retira un examen %s del selector y de la tarjeta al sondear', async (estado, initial) => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance', 'Date'] })
    vi.setSystemTime(Date.parse(initialTime))
    const { state, calls } = mockBackend([initial])
    state.now = () => new Date().toISOString()
    mountPage(undefined, true)
    await advance(0)

    expect(screen.getByRole('combobox', { name: 'Examen' })).toBeInTheDocument()
    const card = screen.queryByRole('complementary', { name: 'Cronómetro del examen' })
    if (initial.cronometro_iniciado_en === null) expect(card).not.toBeInTheDocument()
    else expect(card).toBeInTheDocument()

    // Una vez terminal, el servidor ya no devuelve el examen dentro del listado en curso.
    state.detail = { ...initial, estado }
    state.exams = []
    if (initial.cronometro_iniciado_en === null) fireEvent.focus(window)
    await advance(15_000)

    expect(screen.getByRole('alert')).toHaveTextContent('El examen «Primer Parcial» ya no está disponible')
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
    expect(screen.queryByRole('complementary', { name: 'Cronómetro del examen' })).not.toBeInTheDocument()
    expect(calls.map(({ method }) => method)).toEqual(['GET', 'GET'])
  })
})
