import type { ReactNode } from 'react'
import { act, fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
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
  duracion: 12,
  normas: null,
  id_tipo_examen: 1,
  id_carrera: 1,
  id_materia: 1,
  id_usuario_docente: 'docente',
  materia: { id_materia: 1, nombre: 'Álgebra Lineal', codigo: 'MAT101' },
}
const inProgress: Exam = { ...scheduled, id_examen: 9, nombre_examen: 'Recuperatorio', estado: 'EN_CURSO', duracion: 8 }
const finished: Exam = { ...scheduled, id_examen: 10, nombre_examen: 'Examen finalizado', estado: 'FINALIZADO' }
const cancelled: Exam = { ...scheduled, id_examen: 11, nombre_examen: 'Examen cancelado', estado: 'CANCELADO' }

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

    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    let now = 8_000
    vi.spyOn(performance, 'now').mockImplementation(() => now)
    const display = screen.getByRole('timer', { name: 'Tiempo restante' })
    const startButton = screen.getByRole('button', { name: 'Iniciar tiempo' })

    act(() => vi.advanceTimersByTime(2_000))
    expect(display).toHaveTextContent('00:12:00')
    expect(startButton).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()

    expect(backend.calls).toHaveLength(1)
    await act(async () => { fireEvent.click(startButton) })
    expect(screen.queryByRole('button', { name: 'Iniciar tiempo' })).not.toBeInTheDocument()

    // Un solo tick debe reflejar todo el tiempo real transcurrido desde el inicio.
    now += 119_000
    act(() => vi.advanceTimersByTime(1_000))
    expect(display).toHaveTextContent('00:10:01')
    expect(screen.getByRole('status')).toBeEmptyDOMElement()

    now += 1_000
    fireEvent(document, new Event('visibilitychange'))
    expect(display).toHaveTextContent('00:10:00')
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 10 minutos o menos.')

    now += 600_000
    act(() => vi.advanceTimersByTime(1_000))
    expect(display).toHaveTextContent(/^00:00$/)
    expect(screen.getByRole('status')).toHaveTextContent(/^Tiempo agotado$/)
    expect(screen.getByText('Programado', { exact: true })).toBeInTheDocument()

    now += 60_000
    act(() => vi.advanceTimersByTime(60_000))
    expect(display).toHaveTextContent(/^00:00$/)
    expect(backend.calls.map(({ method, url }) => [method, new URL(url, window.location.href).pathname]))
      .toEqual([['GET', '/api/examenes'], ['GET', '/api/examenes/7']])
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

  it('descarta el reloj al cambiar de examen y muestra el aviso inmediato para duraciones cortas', async () => {
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
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()
  })
})
