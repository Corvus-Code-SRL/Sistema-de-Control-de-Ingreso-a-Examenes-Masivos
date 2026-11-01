import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockApi } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { FinalReport } from '../types/finalReport.types'
import { FinalReportPage } from './FinalReportPage'

const UNAVAILABLE = 'El reporte final solo está disponible para sus exámenes finalizados.'

function makeReport(overrides: Partial<FinalReport> = {}): FinalReport {
  return {
    cabecera: {
      id_examen: 9,
      nombre_examen: 'Parcial final',
      materia: 'Cálculo II',
      docente: 'Ana Rojas',
      fecha: '2026-10-20',
      hora_inicio: '08:14',
      hora_fin: '09:30 (programada)',
      generado_en: '2026-10-20T15:00:00Z'
    },
    asistencia: {
      grupos: [
        {
          id_grupo: 1,
          num_grupo: '1',
          inscritos: 3,
          a_tiempo: 1,
          con_retraso: 1,
          ausentes: 1,
          sin_clasificar: 0
        }
      ],
      totales: { inscritos: 3, a_tiempo: 1, con_retraso: 1, ausentes: 1, sin_clasificar: 0 }
    },
    estudiantes: [
      {
        cod_sis: '202600001',
        nombre: 'Luis Alanes Pinto',
        ambiente: '691A',
        hora_ingreso: '08:05',
        estado: 'A tiempo'
      },
      {
        cod_sis: '202600002',
        nombre: 'Marta Choque Vega',
        ambiente: '691A',
        hora_ingreso: '08:25',
        estado: 'Con retraso'
      },
      {
        cod_sis: '202600003',
        nombre: 'Pedro Zeballos Mamani',
        ambiente: null,
        hora_ingreso: null,
        estado: 'Ausente'
      }
    ],
    incidencias: [],
    ...overrides
  }
}

function mockReport(report: FinalReport = makeReport()) {
  mockApi([
    {
      matches: (url) => url.endsWith('/examenes/9/reporte-final'),
      body: { data: report }
    }
  ])
}

async function renderReport(route = '/examenes/9/reporte-final') {
  renderWithRouter(<FinalReportPage />, {
    route,
    path: '/examenes/:examId/reporte-final'
  })

  await screen.findByRole('heading', { name: /datos del examen/i })
}

describe('FinalReportPage (HU-051)', () => {
  beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:reporte')
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('muestra la cabecera, la asistencia por grupo y los estudiantes', async () => {
    mockReport()
    await renderReport()

    expect(screen.getByText('Parcial final', { selector: 'dd' })).toBeInTheDocument()
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(screen.getByText('Ana Rojas')).toBeInTheDocument()
    expect(screen.getByText('20/10/2026')).toBeInTheDocument()
    expect(screen.getByText('08:14')).toBeInTheDocument()
    expect(screen.getByText('09:30 (programada)')).toBeInTheDocument()

    const totalRow = screen.getByText('Total').closest('tr') as HTMLElement
    const cells = within(totalRow)
      .getAllByRole('cell')
      .map((cell) => cell.textContent)
    expect(cells).toEqual(['Total', '3', '1', '1', '1'])

    expect(screen.getByText('Luis Alanes Pinto')).toBeInTheDocument()
    expect(screen.getByText('Pedro Zeballos Mamani')).toBeInTheDocument()
    expect(screen.getByText('Ausente')).toBeInTheDocument()
  })

  it('muestra la hora de generación en hora de La Paz, sin depender del navegador', async () => {
    mockReport()
    await renderReport()

    expect(screen.getByText(/20\/10\/2026.*11:00/)).toBeInTheDocument()
  })

  it('el enlace Resumen del examen vuelve al examen', async () => {
    mockReport()
    await renderReport()

    const links = screen.getAllByRole('link', { name: /resumen del examen/i })

    expect(links.length).toBeGreaterThan(0)
    links.forEach((link) => expect(link).toHaveAttribute('href', '/examenes/9'))
  })

  it('sin incidencias muestra el mensaje exacto', async () => {
    mockReport()
    await renderReport()

    expect(screen.getByText('No se registraron incidencias en este examen')).toBeInTheDocument()
  })

  it('con incidencias muestra la hora, el estudiante, el tipo y la decisión del docente', async () => {
    mockReport(
      makeReport({
        incidencias: [
          {
            hora: '08:40',
            estudiante: 'Luis Alanes Pinto',
            tipo: 'Copia',
            decision: 'Aprobada',
            observacion: 'Se anula la prueba.'
          }
        ]
      })
    )
    await renderReport()

    expect(screen.queryByText(/no se registraron incidencias/i)).not.toBeInTheDocument()
    expect(screen.getByText('08:40')).toBeInTheDocument()
    expect(screen.getByText('Copia')).toBeInTheDocument()
    expect(screen.getByText('Aprobada: Se anula la prueba.')).toBeInTheDocument()
  })

  it('la columna Sin clasificar solo aparece cuando hay casos', async () => {
    mockReport()
    await renderReport()

    expect(screen.queryByRole('columnheader', { name: /sin clasificar/i })).not.toBeInTheDocument()
  })

  it('muestra la columna Sin clasificar cuando algún estado no se reconoce', async () => {
    const report = makeReport()
    report.asistencia.grupos[0].sin_clasificar = 1
    report.asistencia.totales.sin_clasificar = 1
    mockReport(report)
    await renderReport()

    expect(screen.getByRole('columnheader', { name: /sin clasificar/i })).toBeInTheDocument()
  })

  it('un examen ajeno o no finalizado muestra el mensaje del servidor', async () => {
    mockApi([
      {
        matches: (url) => url.endsWith('/examenes/9/reporte-final'),
        status: 403,
        body: { message: UNAVAILABLE }
      }
    ])
    renderWithRouter(<FinalReportPage />, {
      route: '/examenes/9/reporte-final',
      path: '/examenes/:examId/reporte-final'
    })

    expect(await screen.findByText(UNAVAILABLE)).toBeInTheDocument()
    expect(screen.getByText('Sin permiso')).toBeInTheDocument()
  })

  it('un identificador inválido no consulta al servidor y dice que no existe', async () => {
    mockApi([])
    renderWithRouter(<FinalReportPage />, {
      route: '/examenes/abc/reporte-final',
      path: '/examenes/:examId/reporte-final'
    })

    expect(await screen.findByText('Examen no encontrado')).toBeInTheDocument()
  })

  it('Imprimir usa la impresión del navegador', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => undefined)
    mockReport()
    await renderReport()

    fireEvent.click(screen.getByRole('button', { name: /imprimir/i }))

    expect(print).toHaveBeenCalledTimes(1)
  })

  it('Descargar PDF pide el archivo y lo guarda con el nombre del examen y la fecha', async () => {
    let savedAs = ''
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement
    ) {
      savedAs = this.download
    })
    mockApi([
      {
        matches: (url) => url.endsWith('/examenes/9/reporte-final/pdf'),
        body: new Blob(['%PDF-1.4'])
      },
      {
        matches: (url) => url.endsWith('/examenes/9/reporte-final'),
        body: { data: makeReport() }
      }
    ])
    await renderReport()

    fireEvent.click(screen.getByRole('button', { name: /descargar pdf/i }))

    await waitFor(() => expect(savedAs).toBe('reporte-final-parcial-final-2026-10-20.pdf'))
    expect(URL.createObjectURL).toHaveBeenCalledTimes(1)
  })

  it('si falla la descarga del PDF muestra el mensaje del servidor', async () => {
    mockApi([
      {
        matches: (url) => url.endsWith('/examenes/9/reporte-final/pdf'),
        status: 403,
        body: { message: UNAVAILABLE }
      },
      {
        matches: (url) => url.endsWith('/examenes/9/reporte-final'),
        body: { data: makeReport() }
      }
    ])
    await renderReport()

    fireEvent.click(screen.getByRole('button', { name: /descargar pdf/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(UNAVAILABLE)
  })
})
