import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import type { Exam } from '../types/exams.types'
import { useFinishedExams } from '../hooks/useFinishedExams'
import { ExamReportsPage } from './ExamReportsPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>
}))
vi.mock('../hooks/useFinishedExams')

const mockedHook = vi.mocked(useFinishedExams)

function makeExam(overrides: Partial<Exam> = {}): Exam {
  return {
    id_examen: 7,
    nombre_examen: 'Parcial 1',
    fecha: '2026-10-01',
    hora_inicio: '08:00:00',
    hora_fin: '10:00:00',
    estado: 'FINALIZADO',
    materia: { nombre: 'Calidad de SW' },
    carrera: { nombre: 'Ing. Informática' },
    ...overrides
  } as unknown as Exam
}

function setHook(partial: Partial<ReturnType<typeof useFinishedExams>>) {
  mockedHook.mockReturnValue({
    exams: [],
    isLoading: false,
    isEmpty: true,
    error: null,
    reload: vi.fn(),
    ...partial
  })
}

function renderPage() {
  return render(
    <MemoryRouter>
      <ExamReportsPage />
    </MemoryRouter>
  )
}

describe('ExamReportsPage', () => {
  beforeEach(() => {
    mockedHook.mockReset()
  })

  it('muestra el estado de carga', () => {
    setHook({ isLoading: true })
    renderPage()

    expect(screen.getByText(/cargando sus exámenes finalizados/i)).toBeInTheDocument()
  })

  it('muestra el estado vacío cuando no hay exámenes finalizados', () => {
    setHook({ isEmpty: true })
    renderPage()

    expect(screen.getByText('No tiene exámenes finalizados')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('muestra el error y permite reintentar', () => {
    const reload = vi.fn()
    setHook({ error: new ApiError(500, 'Error del servidor'), reload })
    renderPage()

    expect(screen.getByText(/error del servidor/i)).toBeInTheDocument()
    screen.getByRole('button', { name: /reintentar/i }).click()
    expect(reload).toHaveBeenCalled()
  })

  it('lista los exámenes con fecha y horario formateados', () => {
    setHook({ exams: [makeExam()], isEmpty: false })
    renderPage()

    expect(screen.getByText('Parcial 1')).toBeInTheDocument()
    expect(screen.getByText(/Calidad de SW · Ing\. Informática/)).toBeInTheDocument()
    expect(screen.getByText('01/10/2026')).toBeInTheDocument()
    expect(screen.getByText('08:00–10:00')).toBeInTheDocument()
  })

  it('cada examen enlaza a su reporte final', () => {
    setHook({
      exams: [makeExam({ id_examen: 7 }), makeExam({ id_examen: 9, nombre_examen: 'Parcial 2' })],
      isEmpty: false
    })
    renderPage()

    const links = screen.getAllByRole('link', { name: /generar reporte/i })
    expect(links[0]).toHaveAttribute('href', '/examenes/7/reporte-final')
    expect(links[1]).toHaveAttribute('href', '/examenes/9/reporte-final')
  })
})
