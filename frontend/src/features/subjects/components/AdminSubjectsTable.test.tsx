import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AdminSubjectsTable } from './AdminSubjectsTable'
import { renderWithRouter } from '@/test/render'
import type { AdminSubjectSummary } from '../types/subject.types'

const subjects: AdminSubjectSummary[] = [
  { id_materia: 10, nombre: 'Bases de Datos I', codigo: '2008057', estado: 'ACTIVO' },
  { id_materia: 20, nombre: 'Calculo II', codigo: '2008058', estado: 'INACTIVO' },
]

describe('AdminSubjectsTable', () => {
  it('muestra una fila por materia con código, nombre y estado', () => {
    renderWithRouter(<AdminSubjectsTable subjects={subjects} />, { route: '/materias' })

    const rows = within(screen.getByRole('table')).getAllByRole('row').slice(1)

    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByText('2008057')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Bases de Datos I')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Activo')).toBeInTheDocument()
    expect(within(rows[1]).getByText('2008058')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Calculo II')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Inactivo')).toBeInTheDocument()
  })

  it('es de solo lectura: sin enlaces ni botones de acción', () => {
    renderWithRouter(<AdminSubjectsTable subjects={subjects} />, { route: '/materias' })

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.queryByText(/editar/i)).not.toBeInTheDocument()
  })
})
