import { render, screen } from '@testing-library/react'
import { expect, it, vi } from 'vitest'
import { EntryExamCard } from './EntryExamCard'
import type { EntryContext, EntryStatus } from '../types/entry-control.types'

const context: EntryContext = {
  id_examen: 7, nombre_examen: 'Primer parcial', fecha: '2026-10-16',
  hora_inicio: '10:00', hora_fin: '12:00', estado: 'EN_INGRESO',
  materia: 'Cálculo I', carrera: 'Ing. de Sistemas', grupos: ['1'],
  rol_controlador: 'AUXILIAR', id_ambiente_asignado: 5,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A' }],
}
const status: EntryStatus = {
  ingresados: 1, pendientes: 1, total: 2, ultimos_ingresos: [], version: 'v1',
}

it('muestra En vivo solo mientras el estado está actualizado', () => {
  const props = { context, status, roomId: 5, onRoomChange: vi.fn() }
  const { rerender } = render(<EntryExamCard {...props} />)

  expect(screen.getByText('En vivo')).toBeInTheDocument()
  rerender(<EntryExamCard {...props} stale />)
  expect(screen.queryByText('En vivo')).not.toBeInTheDocument()
})
