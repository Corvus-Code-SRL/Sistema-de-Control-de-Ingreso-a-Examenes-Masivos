import type { ReactNode } from 'react'
import { act, fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { examsService } from '../services/examsService'
import type { Exam } from '../types/exams.types'
import { ExamTimerPage } from './ExamTimerPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))

const scheduled: Exam = {
  id_examen: 7,
  nombre_examen: 'Primer Parcial',
  estado: 'PROGRAMADO',
  fecha: '2026-11-11',
  hora_inicio: '10:00',
  hora_fin: '10:12',
  inicio_programado: '2026-11-11T10:00:00-04:00',
  duracion: 12,
  normas: null,
  id_tipo_examen: 1,
  id_carrera: 1,
  id_materia: 1,
  id_usuario_docente: 'docente',
  materia: { id_materia: 1, nombre: 'Álgebra Lineal', codigo: 'MAT101' },
}
const inProgress: Exam = { ...scheduled, id_examen: 9, nombre_examen: 'Recuperatorio', estado: 'EN_CURSO', duracion: 8, hora_fin: '10:08' }
const finished: Exam = { ...scheduled, id_examen: 10, nombre_examen: 'Examen finalizado', estado: 'FINALIZADO' }
const cancelled: Exam = { ...scheduled, id_examen: 11, nombre_examen: 'Examen cancelado', estado: 'CANCELADO' }
const scheduledStart = Date.parse('2026-11-11T14:00:00Z')

function useTestClock(start = scheduledStart) {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] })
  const initialTick = performance.now()
  vi.spyOn(Date, 'now').mockImplementation(() => start + performance.now() - initialTick)
}

async function advanceClock(ms: number) {
  await act(async () => { await vi.advanceTimersByTimeAsync(ms) })
}

async function clickStart() {
  await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'Iniciar tiempo' })) })
}

function mockCurrentExam(readDetail: () => Exam, exams = [scheduled]) {
  return mockApiWith(({ url, method }) => {
    if (method !== 'GET') return undefined
    if (url.endsWith('/examenes')) return { body: { data: exams } }
    if (url.endsWith('/examenes/7')) return { body: { data: readDetail() } }
    return undefined
  })
}

function listExams(exams: Exam[], details = exams) {
  return mockApiWith(({ url, method }) => {
    if (method !== 'GET') return undefined
    if (url.endsWith('/examenes')) return { body: { data: exams } }

    const exam = details.find(({ id_examen }) => url.endsWith(`/examenes/${id_examen}`))
    return exam ? { body: { data: exam } } : undefined
  })
}

async function openExamList(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
}

function openExamListWithKeyboard() {
  fireEvent.keyDown(screen.getByRole('combobox', { name: 'Examen' }), { key: 'ArrowDown' })
}

async function renderWithScheduledExamSelected() {
  const user = userEvent.setup()
  renderWithRouter(<ExamTimerPage />)
  await openExamList(user)
  await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))
  return user
}

function deferDetailResponse() {
  let resolve!: (exam: Exam) => void
  const response = new Promise<Exam>((complete) => { resolve = complete })
  const getExam = vi.spyOn(examsService, 'getExam').mockReturnValueOnce(response)
  return { getExam, resolve }
}

describe('HU-038 — Controlar tiempo del examen', () => {
  beforeEach(() => {
    vi.spyOn(Date, 'now').mockReturnValue(scheduledStart)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('muestra la carga y luego el estado vacío si todos los exámenes están cerrados', async () => {
    listExams([finished, cancelled])
    renderWithRouter(<ExamTimerPage />)

    expect(screen.getByRole('status')).toHaveTextContent('Cargando sus exámenes')
    expect(await screen.findByText('No tiene exámenes disponibles')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('muestra el error de la consulta y permite reintentar', async () => {
    mockApiWith(({ url }) => url.endsWith('/examenes')
      ? { status: 500, body: { message: 'No se pudo consultar sus exámenes.' } }
      : undefined)
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar la información')
    listExams([scheduled])
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('combobox', { name: 'Examen' })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('solo ofrece exámenes programados y en curso y muestra los datos del examen elegido', async () => {
    listExams([scheduled, inProgress, finished, cancelled])
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)

    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
    await openExamList(user)
    expect(screen.getAllByRole('option')).toHaveLength(2)
    expect(screen.getByRole('option', { name: /Recuperatorio/ })).toBeInTheDocument()
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))

    expect(screen.getByRole('heading', { name: 'Primer Parcial' })).toBeInTheDocument()
    expect(screen.getByText('Programado', { exact: true })).toBeInTheDocument()
    expect(screen.getByText('10:00–10:12')).toBeInTheDocument()
    expect(screen.getByText('12 min')).toBeInTheDocument()
  })

  it('empieza manualmente, calcula el restante desde el instante de inicio y se detiene en cero sin escribir', async () => {
    const backend = listExams([scheduled])
    await renderWithScheduledExamSelected()

    const display = screen.getByRole('timer', { name: 'Tiempo restante' })
    const startButton = screen.getByRole('button', { name: 'Iniciar tiempo' })

    expect(display).toHaveTextContent('00:12:00')
    expect(startButton).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()

    expect(backend.calls).toHaveLength(1)
    useTestClock()
    await clickStart()
    expect(display).toHaveTextContent('00:12:00')
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()

    await advanceClock(4_999)
    expect(backend.calls).toHaveLength(2)
    await advanceClock(1)
    expect(backend.calls).toHaveLength(3)

    await advanceClock(114_000)
    expect(display).toHaveTextContent('00:10:01')
    expect(screen.getByRole('status')).toBeEmptyDOMElement()

    await advanceClock(1_000)
    expect(display).toHaveTextContent('00:10:00')
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 10 minutos o menos.')

    vi.spyOn(Date, 'now').mockReturnValue(scheduledStart + 720_000)
    fireEvent(document, new Event('visibilitychange'))
    expect(display).toHaveTextContent(/^00:00$/)
    expect(screen.getByRole('status')).toHaveTextContent(/^Tiempo agotado$/)
    expect(screen.getByText('Programado', { exact: true })).toBeInTheDocument()

    const totalCalls = backend.calls.length
    await advanceClock(60_000)
    expect(display).toHaveTextContent(/^00:00$/)
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
    expect(backend.calls).toHaveLength(totalCalls)
    expect(backend.calls.every(({ method }) => method === 'GET')).toBe(true)
  })

  it('usa la duración y el estado elegible devueltos por el detalle', async () => {
    const latest = { ...inProgress, id_examen: scheduled.id_examen, nombre_examen: scheduled.nombre_examen }
    listExams([scheduled], [latest])
    const user = await renderWithScheduledExamSelected()

    await user.click(screen.getByRole('button', { name: 'Iniciar tiempo' }))

    expect(await screen.findByText('En curso', { exact: true })).toBeInTheDocument()
    expect(screen.getByText('8 min')).toBeInTheDocument()
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:08:00$/)
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
  })

  it('avisa antes del inicio tardío y vence exactamente a la hora programada', async () => {
    const latest = {
      ...scheduled,
      hora_inicio: '14:00',
      hora_fin: '16:00',
      inicio_programado: '2026-11-11T14:00:00-04:00',
      duracion: 120,
    }
    const backend = listExams([scheduled], [latest])
    await renderWithScheduledExamSelected()
    const endAt = Date.parse('2026-11-11T20:00:00Z')
    useTestClock(endAt - 100 * 60_000)

    await clickStart()
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 100 min de los 120 min previstos, hasta las 16:00.')
    await advanceClock(5_000)
    expect(backend.calls).toHaveLength(2)

    const confirm = screen.getByRole('button', { name: 'Iniciar con el tiempo disponible' })
    await act(async () => { fireEvent.click(confirm) })
    expect(backend.calls).toHaveLength(3)
    expect(screen.getByRole('timer')).toHaveTextContent(/^01:39:55$/)

    vi.spyOn(Date, 'now').mockReturnValue(endAt - 1)
    fireEvent(document, new Event('visibilitychange'))
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:00:01$/)
    vi.spyOn(Date, 'now').mockReturnValue(endAt)
    await advanceClock(1)
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:00$/)
    expect(screen.getByRole('status')).toHaveTextContent(/^Tiempo agotado$/)
  })

  it.each([0, 1_000])('rechaza el inicio si alcanzó o superó el fin por %d ms', async (offset) => {
    listExams([scheduled])
    await renderWithScheduledExamSelected()
    vi.spyOn(Date, 'now').mockReturnValue(scheduledStart + 12 * 60_000 + offset)

    await clickStart()

    expect(await screen.findByRole('alert')).toHaveTextContent('La hora de fin programada ya pasó.')
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
  })

  it('revalida la hora al confirmar y rechaza el inicio si el tiempo se agotó mientras se mostraba el aviso', async () => {
    listExams([scheduled])
    const user = await renderWithScheduledExamSelected()
    vi.spyOn(Date, 'now').mockReturnValue(scheduledStart + 5 * 60_000)
    await clickStart()
    const confirm = screen.getByRole('button', { name: 'Iniciar con el tiempo disponible' })

    vi.spyOn(Date, 'now').mockReturnValue(scheduledStart + 12 * 60_000)
    await user.click(confirm)

    expect(await screen.findByRole('alert')).toHaveTextContent('La hora de fin programada ya pasó.')
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:12:00$/)
  })

  it('respeta el desfase de la API al calcular un fin que cruza la medianoche', async () => {
    const latest = {
      ...scheduled,
      hora_inicio: '23:54',
      hora_fin: '00:06',
      inicio_programado: '2026-11-11T23:54:00-04:00',
    }
    listExams([scheduled], [latest])
    const user = await renderWithScheduledExamSelected()
    vi.spyOn(Date, 'now').mockReturnValue(Date.parse('2026-11-12T04:01:00Z'))

    await clickStart()
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 5 min de los 12 min previstos, hasta las 00:06.')
    await user.click(screen.getByRole('button', { name: 'Iniciar con el tiempo disponible' }))
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:05:00$/)
  })

  it('rechaza una fecha de inicio sin desfase horario', async () => {
    listExams([scheduled], [{ ...scheduled, inicio_programado: '2026-11-11T10:00:00' }])
    await renderWithScheduledExamSelected()

    await clickStart()

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo determinar la hora de fin programada')
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
  })

  it.each(['FINALIZADO', 'CANCELADO'] as const)('rechaza el inicio si el detalle devuelve %s', async (estado) => {
    listExams([scheduled], [{ ...scheduled, estado }])
    const user = await renderWithScheduledExamSelected()

    await user.click(screen.getByRole('button', { name: 'Iniciar tiempo' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('El examen ya no está disponible para iniciar el cronómetro.')
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:12:00$/)
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
  })

  it.each([
    ['red', 'No se pudo verificar el estado del examen. Intente nuevamente.'],
    ['autorización', 'No tiene autorización para consultar este examen.'],
  ])('permite reintentar tras un error de %s del detalle', async (failure, message) => {
    let detailFails = true
    mockApiWith(({ url, method }) => {
      if (method !== 'GET') return undefined
      if (url.endsWith('/examenes')) return { body: { data: [scheduled] } }
      if (!url.endsWith('/examenes/7')) return undefined
      if (!detailFails) return { body: { data: scheduled } }
      if (failure === 'red') throw new TypeError('Failed to fetch')
      return { status: 403 }
    })
    const user = await renderWithScheduledExamSelected()

    const startButton = screen.getByRole('button', { name: 'Iniciar tiempo' })
    await user.click(startButton)

    expect(await screen.findByRole('alert')).toHaveTextContent(message)
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:12:00$/)
    expect(startButton).toBeEnabled()

    detailFails = false
    await act(async () => { fireEvent.click(startButton) })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
  })

  it('consulta una sola vez aunque se pulse repetidamente durante la verificación', async () => {
    listExams([scheduled])
    const { getExam, resolve } = deferDetailResponse()
    await renderWithScheduledExamSelected()
    const startButton = screen.getByRole('button', { name: 'Iniciar tiempo' })

    act(() => {
      fireEvent.click(startButton)
      fireEvent.click(startButton)
    })
    expect(getExam).toHaveBeenCalledTimes(1)
    expect(startButton).toBeDisabled()
    expect(screen.getByRole('status')).toHaveTextContent('Verificando estado del examen…')

    await act(async () => { resolve(scheduled) })
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
    expect(getExam).toHaveBeenCalledTimes(1)
  })

  it('ignora el detalle tardío si se cambia de examen durante la verificación', async () => {
    listExams([scheduled, inProgress])
    const { getExam, resolve } = deferDetailResponse()
    const user = await renderWithScheduledExamSelected()
    await user.click(screen.getByRole('button', { name: 'Iniciar tiempo' }))
    const signal = getExam.mock.calls[0][1]

    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Recuperatorio/ }))
    expect(signal?.aborted).toBe(true)

    await act(async () => { resolve(scheduled) })
    expect(screen.getByRole('heading', { name: 'Recuperatorio' })).toBeInTheDocument()
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:08:00$/)
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('mantiene el inicio único al volver a elegir el examen y avisa desde el inicio para duraciones cortas', async () => {
    listExams([scheduled, inProgress])
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Recuperatorio/ }))
    await user.click(screen.getByRole('button', { name: 'Iniciar tiempo' }))
    expect(await screen.findByText('Quedan 10 minutos o menos.')).toBeInTheDocument()

    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Recuperatorio/ }))

    expect(screen.getByRole('timer')).toHaveTextContent('00:08:00')
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 10 minutos o menos.')
  })

  it.each([
    ['FINALIZADO', 'finalizado'],
    ['CANCELADO', 'cancelado'],
  ] as const)('detiene la cuenta y retira el examen si el detalle devuelve %s', async (estado, condition) => {
    let detail = scheduled
    const backend = mockCurrentExam(() => detail, [scheduled, inProgress])
    await renderWithScheduledExamSelected()
    useTestClock()
    await clickStart()

    detail = { ...scheduled, estado }
    await advanceClock(5_000)

    expect(screen.getByRole('alert')).toHaveTextContent(`El examen «Primer Parcial» fue ${condition} y ya no está disponible.`)
    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
    openExamListWithKeyboard()
    expect(screen.getByRole('option', { name: /Recuperatorio/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Primer Parcial/ })).not.toBeInTheDocument()
    await advanceClock(10_000)
    expect(backend.calls.map(({ method }) => method)).toEqual(['GET', 'GET', 'GET'])
  })

  it('ante un fallo temporal informa el error, mantiene la cuenta y vuelve a consultar', async () => {
    let offline = false
    const backend = mockCurrentExam(() => {
      if (offline) throw new TypeError('Failed to fetch')
      return { ...scheduled, estado: 'EN_CURSO' }
    })
    await renderWithScheduledExamSelected()
    useTestClock()
    await clickStart()

    offline = true
    await advanceClock(5_000)
    expect(screen.getByRole('alert')).toHaveTextContent('No se pudo actualizar el estado del examen.')
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:11:55$/)
    await advanceClock(4_999)
    expect(backend.calls).toHaveLength(3)

    offline = false
    await advanceClock(1)
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:11:50$/)
    expect(backend.calls).toHaveLength(4)
  })

  it('pausa las consultas ocultas y al volver verifica sin solapar solicitudes', async () => {
    const backend = listExams([scheduled])
    await renderWithScheduledExamSelected()
    useTestClock()
    await clickStart()
    const { getExam, resolve } = deferDetailResponse()
    const hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
    fireEvent(document, new Event('visibilitychange'))
    await advanceClock(10_000)
    expect(backend.calls).toHaveLength(2)

    hidden.mockReturnValue(false)
    fireEvent(document, new Event('visibilitychange'))
    await advanceClock(1)
    expect(getExam).toHaveBeenCalledTimes(1)
    fireEvent(document, new Event('visibilitychange'))
    await advanceClock(10_000)
    expect(getExam).toHaveBeenCalledTimes(1)

    await act(async () => { resolve({ ...scheduled, estado: 'EN_CURSO' }) })
    expect(screen.getByText('En curso', { exact: true })).toBeInTheDocument()
    await advanceClock(4_999)
    expect(getExam).toHaveBeenCalledTimes(1)
    await advanceClock(1)
    expect(getExam).toHaveBeenCalledTimes(2)
  })

  it('cancela la consulta activa al cambiar de examen e ignora un cierre tardío', async () => {
    listExams([scheduled, inProgress])
    await renderWithScheduledExamSelected()
    useTestClock()
    await clickStart()
    const { getExam, resolve } = deferDetailResponse()
    await advanceClock(5_000)

    openExamListWithKeyboard()
    fireEvent.keyDown(screen.getByRole('option', { name: /Recuperatorio/ }), { key: 'Enter' })
    expect(getExam.mock.calls[0][1]?.aborted).toBe(true)
    await act(async () => { resolve({ ...scheduled, estado: 'FINALIZADO' }) })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()

    openExamListWithKeyboard()
    fireEvent.keyDown(screen.getByRole('option', { name: /Primer Parcial/ }), { key: 'Enter' })
    expect(screen.getByRole('timer')).toHaveTextContent(/^00:11:55$/)
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()
  })

  it('cancela el polling pendiente al salir de la vista', async () => {
    listExams([scheduled])
    const user = userEvent.setup()
    const { unmount } = renderWithRouter(<ExamTimerPage />)
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))
    useTestClock()
    await clickStart()
    const { getExam, resolve } = deferDetailResponse()
    await advanceClock(5_000)

    unmount()
    expect(getExam.mock.calls[0][1]?.aborted).toBe(true)
    await act(async () => { resolve(scheduled) })
    await advanceClock(10_000)
    expect(getExam).toHaveBeenCalledTimes(1)
  })
})
