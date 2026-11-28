import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { UnassignableSubject } from '../types/unassignSubject.types'
import { UnassignSubjectsTable } from './UnassignSubjectsTable'

const subjects: UnassignableSubject[] = [
  { id_materia: 10, codigo: '2008019', nombre: 'Cálculo I', estado: 'ACTIVO', grupos_count: 3 },
  { id_materia: 11, codigo: '2008054', nombre: 'Programación', estado: 'ACTIVO', grupos_count: 0 },
  { id_materia: 12, codigo: '2010010', nombre: 'Inglés I', estado: 'INACTIVO', grupos_count: 0 },
  { id_materia: 13, codigo: '2008140', nombre: 'Física', estado: 'ACTIVO', grupos_count: 1 },
]

function renderTable(
  props: { canChange?: boolean; onUnassign?: () => void; onReactivate?: () => void } = {}
) {
  const onUnassign = props.onUnassign ?? vi.fn()
  const onReactivate = props.onReactivate ?? vi.fn()

  render(
    <UnassignSubjectsTable
      subjects={subjects}
      periodLabel="2/2026"
      canChange={props.canChange ?? true}
      onUnassign={onUnassign}
      onReactivate={onReactivate}
    />
  )

  return { onUnassign, onReactivate }
}

describe('UnassignSubjectsTable', () => {
  it('muestra las columnas con el período en el encabezado de grupos', () => {
    renderTable()

    for (const name of ['Código', 'Materia', 'Grupos en 2/2026', 'Estado', 'Acción']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
  })

  it('muestra código, nombre y el conteo de grupos (singular, plural o sin grupos)', () => {
    renderTable()

    expect(screen.getAllByText('2008019').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Cálculo I').length).toBeGreaterThan(0)
    expect(screen.getAllByText('3 grupos').length).toBeGreaterThan(0)
    expect(screen.getAllByText('1 grupo').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Sin grupos').length).toBeGreaterThan(0)
  })

  it('«Activo» para la oferta activa y «Desvinculada» para la inactiva', () => {
    renderTable()

    expect(screen.getAllByText('Activo')).toHaveLength(3)
    expect(screen.getAllByText('Desvinculada')).toHaveLength(1)
    expect(screen.queryByText('Inactivo')).not.toBeInTheDocument()
  })

  it('la fila desvinculada solo tiene «Reactivar», sin «Desvincular»', () => {
    renderTable()

    expect(screen.getByRole('button', { name: 'Reactivar Inglés I' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Desvincular Inglés I' })).not.toBeInTheDocument()
  })

  it('las filas activas solo tienen «Desvincular», sin «Reactivar»', () => {
    renderTable()

    expect(screen.getByRole('button', { name: 'Desvincular Programación' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reactivar Programación' })).not.toBeInTheDocument()
  })

  it('una materia sin grupos llama a onUnassign con la materia', async () => {
    const user = userEvent.setup()
    const { onUnassign } = renderTable()
    const button = screen.getByRole('button', { name: 'Desvincular Programación' })

    expect(button).not.toHaveAttribute('aria-disabled')

    await user.click(button)

    expect(onUnassign).toHaveBeenCalledWith(subjects[1])
  })

  it('una materia con grupos queda marcada como deshabilitada pero sigue enfocable y avisa', async () => {
    const user = userEvent.setup()
    const { onUnassign } = renderTable()
    const button = screen.getByRole('button', { name: 'Desvincular Cálculo I' })

    expect(button).toHaveAttribute('aria-disabled', 'true')
    expect(button).not.toBeDisabled()

    await user.click(button)

    expect(onUnassign).toHaveBeenCalledWith(subjects[0])
  })

  it('«Reactivar» llama a onReactivate con la materia', async () => {
    const user = userEvent.setup()
    const { onReactivate, onUnassign } = renderTable()

    await user.click(screen.getByRole('button', { name: 'Reactivar Inglés I' }))

    expect(onReactivate).toHaveBeenCalledWith(subjects[2])
    expect(onUnassign).not.toHaveBeenCalled()
  })

  it('en un período de solo consulta no hay ningún botón, tampoco «Reactivar»', () => {
    renderTable({ canChange: false })

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.getAllByLabelText('Sin acción disponible')).toHaveLength(subjects.length)
  })
})
