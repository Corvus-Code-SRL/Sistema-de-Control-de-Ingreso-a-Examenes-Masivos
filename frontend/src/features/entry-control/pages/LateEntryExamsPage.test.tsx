import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
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

function exam(id: number, title: string): InProgressExamRow {
  return { id, title, subject: 'Bases de Datos I', date: '2026-10-31', startsAt: '10:00', endsAt: '12:00', rooms: '691A, 692B' }
}

function withExams(rows: InProgressExamRow[] | null, status: 'loading' | 'success' | 'error' = 'success', error: ApiError | null = null) {
  vi.mocked(useInProgressExams).mockReturnValue({ status, data: rows, error, reload })
}

function renderPage() {
  return renderWithRouter(<LateEntryExamsPage />, { route: '/retrasos', path: '/retrasos' })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('HU-037: exámenes para registrar con retraso', () => {
  it('sin exámenes en curso muestra el estado vacío', () => {
    withExams([])
    renderPage()

    expect(screen.getByText('No tiene exámenes en curso')).toBeInTheDocument()
  })

  it('con un solo examen entra directo a su registro', () => {
    withExams([exam(7, 'Primer parcial')])
    render(
      <MemoryRouter initialEntries={['/retrasos']}>
        <Routes>
          <Route path="/retrasos" element={<LateEntryExamsPage />} />
          <Route path="/examenes/:examId/retrasos" element={<p>Registro del examen</p>} />
        </Routes>
      </MemoryRouter>
    )

    expect(screen.getByText('Registro del examen')).toBeInTheDocument()
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
})
