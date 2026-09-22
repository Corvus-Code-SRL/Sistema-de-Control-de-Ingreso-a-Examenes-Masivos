import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AdminSubjectsTable } from './AdminSubjectsTable'
import { renderWithRouter } from '@/test/render'
import type { AdminSubjectSummary } from '../types/subject.types'

const subjects: AdminSubjectSummary[] = [
  {
    id_materia: 10,
    nombre: 'Bases de Datos I',
    codigo: '2008057',
  },
  {
    id_materia: 20,
    nombre: 'Calculo II',
    codigo: '2008058',
  },
]

describe('AdminSubjectsTable', () => {
  it('muestra una fila por materia institucional', () => {
    renderWithRouter(<AdminSubjectsTable subjects={subjects} />, {
      route: '/materias',
    })

    const table = within(screen.getByRole('table'))

    expect(table.getByText('Bases de Datos I')).toBeInTheDocument()
    expect(table.getByText('2008057')).toBeInTheDocument()
    expect(table.getByText('Calculo II')).toBeInTheDocument()
    expect(table.getByText('2008058')).toBeInTheDocument()
  })

  it('enlaza cada materia con su pantalla de edicion por id_materia', () => {
    renderWithRouter(<AdminSubjectsTable subjects={subjects} />, {
      route: '/materias',
    })

    expect(
      screen.getByRole('link', { name: 'Editar Bases de Datos I' })
    ).toHaveAttribute('href', '/materias/10/editar')

    expect(
      screen.getByRole('link', { name: 'Editar Calculo II' })
    ).toHaveAttribute('href', '/materias/20/editar')
  })
})