import type { ReactNode } from 'react'
import { act, fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
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
const inEntry: Exam = { ...scheduled, id_examen: 8, nombre_examen: 'Segundo Parcial', estado: 'EN_INGRESO' }
const inProgress: Exam = { ...scheduled, id_examen: 9, nombre_examen: 'Recuperatorio', estado: 'EN_CURSO', duracion: 8 }
const finished: Exam = { ...scheduled, id_examen: 10, nombre_examen: 'Examen finalizado', estado: 'FINALIZADO' }
const cancelled: Exam = { ...scheduled, id_examen: 11, nombre_examen: 'Examen cancelado', estado: 'CANCELADO' }

function listExams(exams: Exam[]) {
  return mockApiWith(({ url, method }) =>
    url.endsWith('/examenes') && method === 'GET' ? { body: { data: exams } } : undefined
  )
}

async function openExamList(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
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

  it('ofrece los tres estados operativos y muestra los datos del examen elegido', async () => {
    listExams([scheduled, inEntry, inProgress, finished, cancelled])
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)

    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
    await openExamList(user)
    expect(screen.getAllByRole('option')).toHaveLength(3)
    expect(screen.getByRole('option', { name: /Segundo Parcial/ })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Recuperatorio/ })).toBeInTheDocument()
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))

    expect(screen.getByRole('heading', { name: 'Primer Parcial' })).toBeInTheDocument()
    expect(screen.getByText('Programado', { exact: true })).toBeInTheDocument()
    expect(screen.getByText('10:00–10:12')).toBeInTheDocument()
    expect(screen.getByText('12 min')).toBeInTheDocument()
  })

  it('empieza manualmente, calcula el restante desde el instante de inicio y se detiene en cero sin escribir', async () => {
    const backend = listExams([scheduled])
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))

    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
    let now = 8_000
    vi.spyOn(performance, 'now').mockImplementation(() => now)
    const display = screen.getByRole('timer', { name: 'Tiempo restante' })
    const startButton = screen.getByRole('button', { name: 'Iniciar tiempo' })

    act(() => vi.advanceTimersByTime(2_000))
    expect(display).toHaveTextContent('00:12:00')
    expect(startButton).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()

    fireEvent.click(startButton)
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
    expect(display).toHaveTextContent('00:00:00')
    expect(screen.getByRole('status')).toHaveTextContent('Tiempo cumplido.')
    expect(screen.getByText('Programado', { exact: true })).toBeInTheDocument()

    now += 60_000
    act(() => vi.advanceTimersByTime(60_000))
    expect(display).toHaveTextContent('00:00:00')
    expect(backend.calls.map(({ method, url }) => [method, url.endsWith('/examenes')])).toEqual([['GET', true]])
  })

  it('descarta el reloj al cambiar de examen y muestra el aviso inmediato para duraciones cortas', async () => {
    listExams([scheduled, inProgress])
    const user = userEvent.setup()
    renderWithRouter(<ExamTimerPage />)
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Recuperatorio/ }))
    await user.click(screen.getByRole('button', { name: 'Iniciar tiempo' }))
    expect(screen.getByRole('status')).toHaveTextContent('Quedan 10 minutos o menos.')

    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Primer Parcial/ }))
    await openExamList(user)
    await user.click(screen.getByRole('option', { name: /Recuperatorio/ }))

    expect(screen.getByRole('timer')).toHaveTextContent('00:08:00')
    expect(screen.getByRole('button', { name: 'Iniciar tiempo' })).toBeEnabled()
    expect(screen.getByRole('status')).toBeEmptyDOMElement()
  })
})
