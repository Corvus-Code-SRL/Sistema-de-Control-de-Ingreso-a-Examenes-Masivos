import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { configureSessionHandlers } from '@/lib/api-client'
import { IncidentDetailPanel } from './IncidentDetailPanel'
import type { IncidentReview } from '../types/incident.types'
import { mockApi } from '@/test/http'

const pending: IncidentReview = {
  id: 'estudiante-9926',
  tipo: 'estudiante',
  estado: 'PENDIENTE',
  persona: { nombre_completo: 'José Luis Pérez Núñez', codigo: '202312345', grupo: null, carrera: null, foto_url: null },
  examen: { id_examen: 9807, nombre_examen: 'Cálculo finalizado', fecha: '2026-10-18', materia: 'Cálculo I' },
  tipo_falta: { id: 3, nombre: 'Material no permitido' },
  descripcion: 'Material impreso entre sus útiles.',
  evidencia_url: null,
  evidencia: null,
  reportante: { nombre_completo: 'Daniela Ferrufino', rol: 'Auxiliar' },
  fecha_reporte: '2026-10-18T09:10:00+00:00',
  hora_reporte: '09:10',
  ambiente: { id_ambiente: 9601, nro_aula: '691A' },
  intento: {
    id_intento: 9906,
    motivo: 'DECISION_CONTROLADOR',
    motivo_legible: 'Rechazo por decisión del controlador',
    observacion: null,
    fecha_intento: '2026-10-18T09:10:00+00:00',
    hora_intento: '09:10',
    ambiente: { id_ambiente: 9601, nro_aula: '691A' },
  },
  revisor: null,
  en_central_riesgo: false,
}

const approved: IncidentReview = {
  ...pending,
  estado: 'APROBADO',
  revisor: {
    nombre_completo: 'Marcelo Quiroga',
    fecha_revision: '2026-10-18T10:21:00+00:00',
    observacion_revision: 'Confirmado en la puerta.',
  },
  en_central_riesgo: true,
}

function stubDetail(incident: IncidentReview) {
  mockApi([
    {
      matches: (url) => /\/api\/docente\/incidencias\/estudiante-\d+$/.test(url),
      body: { data: incident },
    },
  ])
}

function renderPanel(incident: IncidentReview, onReviewed = vi.fn()) {
  const onBack = vi.fn()
  stubDetail(incident)
  render(
    <MemoryRouter>
      <IncidentDetailPanel incidentId={incident.id} onReviewed={onReviewed} onBack={onBack} />
    </MemoryRouter>
  )
  return { onReviewed, onBack }
}

afterEach(() => {
  configureSessionHandlers(null)
  vi.unstubAllGlobals()
})

describe('IncidentDetailPanel', () => {
  it('muestra los datos del detalle pendiente: estudiante, examen, motivo, descripción', async () => {
    renderPanel(pending)

    expect(await screen.findByText('José Luis Pérez Núñez')).toBeInTheDocument()
    const subtitle = screen.getByText('202312345').closest('p')
    expect(subtitle).toHaveTextContent(/202312345 · Cálculo finalizado · Cálculo I/)
    expect(screen.getByText('Material no permitido')).toBeInTheDocument()
    expect(screen.getByText('Rechazo por decisión del controlador')).toBeInTheDocument()
    expect(screen.getByText('Material impreso entre sus útiles.')).toBeInTheDocument()
  })

  it('en una revisada muestra la decisión definitiva y no ofrece botones', async () => {
    renderPanel(approved)

    expect(await screen.findByText('Decisión · definitiva')).toBeInTheDocument()
    expect(screen.getByText('Aprobado · en Central de Riesgo')).toBeInTheDocument()
    expect(screen.getByText('Marcelo Quiroga')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aprobar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rechazar' })).not.toBeInTheDocument()
  })

  it('sin evidencia muestra el aviso', async () => {
    renderPanel(pending)

    expect(await screen.findByText('Sin evidencia adjunta.')).toBeInTheDocument()
  })
})
