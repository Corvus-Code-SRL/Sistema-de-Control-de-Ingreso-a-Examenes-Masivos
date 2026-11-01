import type { ReactNode } from 'react'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { renderWithRouter } from '@/test/render'
import { examHistoryService } from '../services/examHistoryService'
import type { HistoryList, HistorySummary } from '../types/examHistory.types'
import { ExamHistoryPage } from './ExamHistoryPage'
import { ExamHistorySummaryPage } from './ExamHistorySummaryPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))
vi.mock('../services/examHistoryService', () => ({
  examHistoryService: { list: vi.fn(), show: vi.fn() },
}))

const list: HistoryList = {
  examenes: [{ id_examen: 7, nombre_examen: 'Examen diagnóstico', fecha: '2026-10-28',
    materia: 'Cálculo II', esperados: 55, presentes: 54, incidencias: 2 }],
  materias: [{ id_materia: 1, nombre: 'Cálculo II' }],
  pagina: 1, paginas: 1, total: 1,
}
const summary: HistorySummary = {
  id_examen: 7, nombre_examen: 'Examen diagnóstico', fecha: '2026-10-28',
  materia: 'Cálculo II', total_estudiantes: 55, inicio_real_en: null, inicio_automatico: false,
  fin_real_en: null, fin_automatico: false,
  totales: { a_tiempo: 51, con_retraso: 1, ausentes: 1, incidencias: 2 },
  linea_tiempo: [{ tipo: 'retraso', fecha_hora: '2026-10-28 08:20:00',
    descripcion: 'Ingreso con retraso', detalle: 'Luis · Controló: Ana Rojas' }],
  ambientes: [{ id_ambiente: 1, nro_aula: '691A', presentes: 54, auxiliares: ['Ana Rojas'] }],
}

beforeEach(() => vi.resetAllMocks())

describe('HU-052: historial de exámenes', () => {
  it('muestra la lista y conserva el filtro de la URL al cargar', async () => {
    vi.mocked(examHistoryService.list).mockResolvedValue(list)
    renderWithRouter(<ExamHistoryPage />, { route: '/historial?materia=1', path: '/historial' })
    expect((await screen.findAllByText('Examen diagnóstico')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('28/10/2026').length).toBeGreaterThan(0)
    expect(screen.getByRole('combobox', { name: 'Filtrar por materia' })).toHaveTextContent('Cálculo II')
    expect(screen.getAllByText('54 / 55').length).toBeGreaterThan(0)
    screen.getAllByRole('link', { name: /ver resumen/i }).forEach((link) => {
      expect(link).toHaveAttribute('href', '/historial/7?materia=1')
    })
    expect(vi.mocked(examHistoryService.list).mock.calls[0][0]).toEqual({ materia: '1', buscar: '', page: '1' })
  })

  it('envía la búsqueda escrita y conserva la materia elegida', async () => {
    vi.mocked(examHistoryService.list).mockResolvedValue(list)
    renderWithRouter(<ExamHistoryPage />, { route: '/historial?materia=1', path: '/historial' })
    await screen.findAllByText('Examen diagnóstico')
    fireEvent.change(screen.getByPlaceholderText('Buscar examen'), {
      target: { value: 'diagnostico' },
    })
    await waitFor(() => expect(examHistoryService.list).toHaveBeenCalledWith(
      { materia: '1', buscar: 'diagnostico', page: '1' }, expect.any(AbortSignal)
    ))
  })

  it('permite avanzar a la segunda página del historial', async () => {
    vi.mocked(examHistoryService.list)
      .mockResolvedValueOnce({ ...list, paginas: 3, total: 25 })
      .mockResolvedValueOnce({ ...list, pagina: 2, paginas: 3, total: 25 })
    renderWithRouter(<ExamHistoryPage />, { route: '/historial', path: '/historial' })
    fireEvent.click(await screen.findByRole('button', { name: 'Siguiente' }))
    await waitFor(() => expect(examHistoryService.list).toHaveBeenCalledWith(
      { materia: '', buscar: '', page: '2' }, expect.any(AbortSignal)
    ))
    expect(await screen.findByText('Página 2 de 3 · 25 exámenes')).toBeInTheDocument()
  })

  it('muestra el mensaje requerido sin exámenes finalizados', async () => {
    vi.mocked(examHistoryService.list).mockResolvedValue({ ...list, examenes: [], total: 0 })
    renderWithRouter(<ExamHistoryPage />, { route: '/historial', path: '/historial' })
    expect(await screen.findByText('Aún no tiene exámenes finalizados')).toBeInTheDocument()
  })

  it('muestra el resumen sin acciones de edición', async () => {
    vi.mocked(examHistoryService.show).mockResolvedValue(summary)
    renderWithRouter(<ExamHistorySummaryPage />, { route: '/historial/7', path: '/historial/:id' })
    expect(await screen.findByText('Examen diagnóstico · Cálculo II')).toBeInTheDocument()
    expect(screen.getByText('Finalizado')).toBeInTheDocument()
    expect(screen.getByText('Total de estudiantes:', { exact: false })).toHaveTextContent('55')
    expect(screen.getByText(/Ingreso con retraso/)).toBeInTheDocument()
    expect(screen.getByText('Luis · Controló: Ana Rojas')).toBeInTheDocument()
    expect(screen.getByText('691A')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /editar|modificar|eliminar|registrar ingreso/i })).not.toBeInTheDocument()
  })

  it('muestra el rechazo de un examen ajeno abierto por URL', async () => {
    vi.mocked(examHistoryService.show).mockRejectedValue(new ApiError(403, 'No tiene acceso a este examen.'))
    renderWithRouter(<ExamHistorySummaryPage />, { route: '/historial/7', path: '/historial/:id' })
    expect(await screen.findByText('No tiene acceso a este examen.')).toBeInTheDocument()
  })
})
