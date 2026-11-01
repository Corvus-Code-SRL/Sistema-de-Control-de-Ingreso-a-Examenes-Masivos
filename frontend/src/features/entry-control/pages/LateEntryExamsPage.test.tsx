import type { ReactNode } from 'react'
import { act, fireEvent, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useInProgressExams, type InProgressExamRow } from '@/features/exams'
import { ApiError } from '@/lib/api-client'
import { renderWithRouter } from '@/test/render'
import { LateEntryExamsPage } from './LateEntryExamsPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))
vi.mock('@/features/exams', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/exams')>()),
  useInProgressExams: vi.fn(),
}))

const reload = vi.fn()

function exam(id: number, title: string, endsAt = '12:00'): InProgressExamRow {
  return { id, title, subject: 'Bases de Datos I', date: '2026-10-31', startsAt: '10:00', endsAt, rooms: '691A, 692B' }
}

function withExams(rows: InProgressExamRow[] | null, status: 'loading' | 'success' | 'error' = 'success', error: ApiError | null = null) {
  vi.mocked(useInProgressExams).mockReturnValue({ status, data: rows, error, reload })
}

function renderPage() {
  return renderWithRouter(<LateEntryExamsPage />, { route: '/retrasos', path: '/retrasos' })
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers({ shouldAdvanceTime: true })
  vi.setSystemTime(new Date('2026-10-31T10:30:00'))
})

afterEach(() => {
  vi.useRealTimers()
})

describe('HU-037: exámenes para registrar con retraso', () => {
  it('sin exámenes en curso muestra el estado vacío', () => {
    withExams([])
    renderPage()

    expect(screen.getByText('No tiene exámenes en curso')).toBeInTheDocument()
  })

  it('con un solo examen también lista, así volver desde el registro no entra en bucle', () => {
    withExams([exam(7, 'Primer parcial')])
    renderPage()

    expect(screen.getByRole('heading', { name: 'Primer parcial' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Registrar con retraso' })).toHaveAttribute('href', '/examenes/7/retrasos')
  })

  it('con varios lista las tarjetas con su acceso', () => {
    withExams([exam(7, 'Primer parcial'), exam(8, 'Segundo parcial')])
    renderPage()

    expect(screen.getByRole('heading', { name: 'Primer parcial' })).toBeInTheDocument()
    expect(screen.getAllByText('Ambientes: 691A, 692B')).toHaveLength(2)
    const links = screen.getAllByRole('link', { name: 'Registrar con retraso' })
    expect(links.map((link) => link.getAttribute('href'))).toEqual(['/examenes/7/retrasos', '/examenes/8/retrasos'])
    expect(links[0]).toHaveClass('h-11')
  })

  it('muestra la carga y el error con reintento', () => {
    withExams(null, 'loading')
    const { unmount } = renderPage()
    expect(screen.getByText('Cargando exámenes en curso')).toBeInTheDocument()
    unmount()

    withExams(null, 'error', new ApiError(500, 'Error del servidor'))
    renderPage()
    expect(screen.getByRole('button', { name: /Reintentar/ })).toBeInTheDocument()
  })

  it('solo lista los exámenes cuyo fin programado no pasó', () => {
    withExams([exam(7, 'Primer parcial', '10:20'), exam(8, 'Segundo parcial')])
    renderPage()

    expect(screen.queryByRole('heading', { name: 'Primer parcial' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Segundo parcial' })).toBeInTheDocument()
  })

  it('al llegar el fin programado el examen sale de la lista sin esperar el cierre', async () => {
    withExams([exam(7, 'Primer parcial', '10:31'), exam(8, 'Segundo parcial')])
    renderPage()
    expect(screen.getByRole('heading', { name: 'Primer parcial' })).toBeInTheDocument()

    await act(async () => { await vi.advanceTimersByTimeAsync(60_000) })

    expect(screen.queryByRole('heading', { name: 'Primer parcial' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Segundo parcial' })).toBeInTheDocument()
  })

  it('recarga al volver a la pestaña y con «Actualizar»', () => {
    withExams([exam(8, 'Segundo parcial')])
    renderPage()

    act(() => { document.dispatchEvent(new Event('visibilitychange')) })
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar' }))

    expect(reload).toHaveBeenCalledTimes(2)
  })
})
