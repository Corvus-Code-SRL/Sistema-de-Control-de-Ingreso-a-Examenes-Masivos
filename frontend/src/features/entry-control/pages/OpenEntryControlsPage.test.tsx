import { act, screen } from '@testing-library/react'
import { expect, it, vi } from 'vitest'
import { mockApi, mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { OpenEntryControlsPage } from './OpenEntryControlsPage'

it('muestra al creador su examen programado con acceso al estado del control', async () => {
  mockApiOnce({ body: { data: [{
    id_examen: 7, nombre_examen: 'Primer parcial', estado: 'PROGRAMADO',
    fecha: '2026-09-29', hora_inicio: '08:00', materia: 'Bases de Datos I', ambientes: ['691A'],
  }] } })

  renderWithRouter(<OpenEntryControlsPage />)

  expect(await screen.findByText('Primer parcial')).toBeInTheDocument()
  expect(screen.getByText('Programado')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /Ver estado del control/ })).toHaveAttribute('href', '/examenes/7/control-ingreso')
})

it('actualiza la lista cuando se abre el ingreso', async () => {
  const exam = {
    id_examen: 7, nombre_examen: 'Primer parcial', estado: 'PROGRAMADO',
    fecha: '2026-09-29', hora_inicio: '08:00', materia: 'Bases de Datos I', ambientes: ['691A'],
  }
  const apiRoutes = [{ matches: () => true, body: { data: [exam] } }]
  mockApi(apiRoutes)
  const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })

  vi.useFakeTimers()
  try {
    await act(async () => { renderWithRouter(<OpenEntryControlsPage />) })
    expect(screen.getByText('Programado')).toBeInTheDocument()
    apiRoutes[0].body = { data: [{ ...exam, estado: 'EN_INGRESO' }] }

    await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
    expect(screen.getByText('Ingreso abierto')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Controlar ingreso/ })).toBeInTheDocument()
  } finally {
    vi.useRealTimers()
    if (hidden) Object.defineProperty(document, 'hidden', hidden)
  }
})
