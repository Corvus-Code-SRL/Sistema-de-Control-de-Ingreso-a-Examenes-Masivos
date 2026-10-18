import { act, screen } from '@testing-library/react'
import { expect, it, vi } from 'vitest'
import { mockApi, mockApiOnce, mockNetworkFailure } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { OpenEntryControlsPage } from './OpenEntryControlsPage'

const exam = {
  id_examen: 7, nombre_examen: 'Primer parcial', estado: 'PROGRAMADO' as 'PROGRAMADO' | 'EN_INGRESO',
  fecha: '2026-10-20', hora_inicio: '08:00', materia: 'Bases de Datos I',
  carrera: 'Ingeniería de Sistemas', ambientes: ['691A', '692B'],
  rol_controlador: 'DOCENTE' as const, id_ambiente_asignado: null, ambiente_asignado: null,
  apertura_programada_en: '2026-10-20T07:50:00-04:00', motivo_apertura: null,
  total: 2, ingresados: 0, pendientes: 2,
}

it('muestra al docente la apertura prevista y el progreso del examen abierto', async () => {
  mockApiOnce({ body: { data: [{ ...exam, estado: 'EN_INGRESO', ingresados: 1, pendientes: 1 }] } })
  renderWithRouter(<OpenEntryControlsPage />)

  expect(await screen.findByText('Primer parcial')).toBeInTheDocument()
  expect(screen.getByText('Ingreso abierto')).toBeInTheDocument()
  expect(screen.getByText('1 de 2 ingresaron · 1 pendientes')).toBeInTheDocument()
  expect(screen.getByText('691A, 692B')).toBeInTheDocument()
  expect(screen.getByText('Apertura programada: 07:50')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /Controlar ingreso/ })).toHaveAttribute('href', '/examenes/7/control-ingreso')
})

it.each([
  ['2026-10-17T07:50:00-04:00', '3 días'],
  ['2026-10-19T07:50:00-04:00', '1 día'],
  ['2026-10-19T08:50:00-04:00', '23 horas'],
  ['2026-10-20T05:50:00-04:00', '2 horas'],
  ['2026-10-20T05:51:00-04:00', '119 minutos'],
  ['2026-10-20T07:40:00-04:00', '10 minutos'],
  ['2026-10-20T07:49:00-04:00', '1 minuto'],
])('indica que faltan %s como %s', async (now, remaining) => {
  vi.useFakeTimers()
  try {
    vi.setSystemTime(new Date(now))
    mockApiOnce({ body: { data: [exam] } })
    await act(async () => { renderWithRouter(<OpenEntryControlsPage />) })

    expect(screen.getByText(`Se abrirá automáticamente a las 07:50 · ${remaining.startsWith('1 ') ? 'falta' : 'faltan'} ${remaining}.`)).toBeInTheDocument()
  } finally {
    vi.useRealTimers()
  }
})

it('muestra el motivo de apertura fallida y el ambiente del auxiliar', async () => {
  mockApiOnce({ body: { data: [{
    ...exam, rol_controlador: 'AUXILIAR', id_ambiente_asignado: 8,
    ambiente_asignado: '692B',
    motivo_apertura: { codigo: 'CAPACIDAD_INSUFICIENTE', mensaje: 'Capacidad 1 menor que la nómina de 2.' },
  }] } })
  renderWithRouter(<OpenEntryControlsPage />)

  expect(await screen.findByText('No se pudo abrir')).toBeInTheDocument()
  expect(screen.getByRole('alert')).toHaveTextContent('Capacidad 1 menor que la nómina de 2.')
  expect(screen.getByText('Agregue un ambiente al examen para que el control se abra.')).toBeInTheDocument()
  expect(screen.getByText('Usted controla en 692B')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /Ver detalle del examen/ })).toBeInTheDocument()
})

it('actualiza la lista cuando se abre el ingreso', async () => {
  const apiRoutes = [{ matches: () => true, body: { data: [exam] } }]
  mockApi(apiRoutes)
  const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })

  vi.useFakeTimers()
  try {
    await act(async () => { renderWithRouter(<OpenEntryControlsPage />) })
    expect(screen.getByText('Programado')).toBeInTheDocument()
    apiRoutes[0].body = { data: [{ ...exam, estado: 'EN_INGRESO', ingresados: 1, pendientes: 1 }] }

    await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
    expect(screen.getByText('Ingreso abierto')).toBeInTheDocument()
    expect(screen.getByText('1 de 2 ingresaron · 1 pendientes')).toBeInTheDocument()
  } finally {
    vi.useRealTimers()
    if (hidden) Object.defineProperty(document, 'hidden', hidden)
  }
})

it('conserva la lista y avisa si falla una actualización; retira el aviso al recuperarse', async () => {
  const apiRoutes = [{ matches: () => true, body: { data: [exam] }, status: 200 }]
  mockApi(apiRoutes)
  const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })
  vi.useFakeTimers()
  try {
    await act(async () => { renderWithRouter(<OpenEntryControlsPage />) })
    expect(screen.getByText('Primer parcial')).toBeInTheDocument()
    apiRoutes[0].status = 503

    await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
    expect(screen.getByRole('alert')).toHaveTextContent('Datos sin actualizar')
    expect(screen.getByText('Primer parcial')).toBeInTheDocument()

    apiRoutes[0].status = 200
    await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
    expect(screen.queryByText(/Datos sin actualizar/)).not.toBeInTheDocument()
  } finally {
    vi.useRealTimers()
    if (hidden) Object.defineProperty(document, 'hidden', hidden)
  }
})

it('explica el fallo y permite reintentar si la lista nunca cargó', async () => {
  mockNetworkFailure()
  renderWithRouter(<OpenEntryControlsPage />)

  expect(await screen.findByText('No se pudieron cargar los controles de ingreso.')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
})
