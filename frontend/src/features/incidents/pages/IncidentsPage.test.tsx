import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { mockApiWith } from '@/test/http'
import { IncidentsPage } from './IncidentsPage'
import type { IncidentReview } from '../types/incident.types'
import { mockApi } from '@/test/http'

const pendiente: IncidentReview = {
  id: 'estudiante-9926',
  tipo: 'estudiante',
  estado: 'PENDIENTE',
  persona: { nombre_completo: 'José Luis Pérez Núñez', codigo: '202312345', grupo: null, carrera: null, foto_url: null },
  examen: { id_examen: 9807, nombre_examen: 'Cálculo finalizado', fecha: '2026-10-18', materia: 'Cálculo I' },
  tipo_falta: { id: 3, nombre: 'Material no permitido' },
  descripcion: 'Material impreso.',
  evidencia_url: null,
  evidencia: null,
  reportante: { nombre_completo: 'Daniela Ferrufino', rol: 'Auxiliar' },
  fecha_reporte: '2026-10-18T09:10:00+00:00',
  hora_reporte: '09:10',
  ambiente: null,
  intento: null,
  revisor: null,
  en_central_riesgo: false,
}

function stubList(pendientes: IncidentReview[], revisadas: IncidentReview[] = []) {
  mockApi([
    {
      matches: (url) => url.includes('/api/docente/incidencias') && url.includes('estado=pendiente'),
      body: { data: pendientes, meta: { total_pendientes: pendientes.length, total_revisadas: revisadas.length } },
    },
    {
      matches: (url) => url.includes('/api/docente/incidencias') && url.includes('estado=revisada'),
      body: { data: revisadas, meta: { total_pendientes: pendientes.length, total_revisadas: revisadas.length } },
    },
  ])
}

function renderPage() {
  renderWithRouter(<IncidentsPage />, { route: '/incidencias', path: '/incidencias' })
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('IncidentsPage', () => {
  it('muestra las pestañas y el contador de pendientes', async () => {
    stubList([pendiente, { ...pendiente, id: 'estudiante-9927' }])
    renderPage()

    expect(screen.getByRole('tab', { name: /Pendientes/ })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: /Revisadas/ })).toHaveAttribute('aria-selected', 'false')

    expect(await screen.findAllByText('José Luis Pérez Núñez')).toHaveLength(2)
    expect(screen.getByText('2 pendientes')).toBeInTheDocument()
  })

  it('sin pendientes muestra el empty state', async () => {
    stubList([])
    renderPage()

    expect(await screen.findByText('No hay incidencias pendientes de revisión')).toBeInTheDocument()
  })

  it('cambiar a Revisadas muestra las revisadas', async () => {
    const revisada = { ...pendiente, estado: 'APROBADO' as const, en_central_riesgo: true }
    stubList([pendiente], [revisada])
    renderPage()
    const user = userEvent.setup()

    await user.click(screen.getByRole('tab', { name: /Revisadas/ }))

    await waitFor(() => expect(screen.getByRole('tab', { name: /Revisadas/ })).toHaveAttribute('aria-selected', 'true'))
  })
})
