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

function renderTable(props: { canUnassign?: boolean; onAction?: () => void } = {}) {
  const onAction = props.onAction ?? vi.fn()

  render(
    <UnassignSubjectsTable
      subjects={subjects}
      periodLabel="2/2026"
      canUnassign={props.canUnassign ?? true}
      onAction={onAction}
    />
  )

  return { onAction }
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

    expect(screen.getByText('2008019')).toBeInTheDocument()
    expect(screen.getByText('Cálculo I')).toBeInTheDocument()
    expect(screen.getByText('3 grupos')).toBeInTheDocument()
    expect(screen.getByText('1 grupo')).toBeInTheDocument()
    expect(screen.getAllByText('Sin grupos')).toHaveLength(2)
  })

  it('«Activo» para la oferta activa y «Desvinculada» para la inactiva', () => {
    renderTable()

    expect(screen.getAllByText('Activo')).toHaveLength(3)
    expect(screen.getAllByText('Desvinculada')).toHaveLength(1)
    expect(screen.queryByText('Inactivo')).not.toBeInTheDocument()
  })

  it('la fila desvinculada no tiene botón', () => {
    renderTable()

    expect(screen.queryByRole('button', { name: 'Desvincular Inglés I' })).not.toBeInTheDocument()
  })

  it('una materia sin grupos llama a onAction con la materia', async () => {
    const user = userEvent.setup()
    const { onAction } = renderTable()
    const button = screen.getByRole('button', { name: 'Desvincular Programación' })

    expect(button).not.toHaveAttribute('aria-disabled')

    await user.click(button)

    expect(onAction).toHaveBeenCalledWith(subjects[1])
  })

  it('una materia con grupos queda marcada como deshabilitada pero sigue enfocable y avisa', async () => {
    const user = userEvent.setup()
    const { onAction } = renderTable()
    const button = screen.getByRole('button', { name: 'Desvincular Cálculo I' })

    expect(button).toHaveAttribute('aria-disabled', 'true')
    expect(button).not.toBeDisabled()

    await user.click(button)

    expect(onAction).toHaveBeenCalledWith(subjects[0])
  })

  it('en un período de solo consulta no hay ningún botón', () => {
    renderTable({ canUnassign: false })

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.getAllByLabelText('Sin acción disponible')).toHaveLength(subjects.length)
  })
})
