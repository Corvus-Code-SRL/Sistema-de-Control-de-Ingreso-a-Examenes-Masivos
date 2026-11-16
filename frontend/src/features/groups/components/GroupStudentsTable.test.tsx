import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { GroupStudentsTable } from './GroupStudentsTable'
import type { GroupCareer, GroupStudentsPage } from '../types/group.types'
import { makeGroupStudent } from '@/test/groupStudentFixtures'
import { renderWithRouter } from '@/test/render'

const sistemas: GroupCareer = { id_carrera: 1, codigo: 'SIS', nombre: 'Ingeniería de Sistemas', principal: true }
const informatica: GroupCareer = { id_carrera: 2, codigo: 'INF', nombre: 'Ingeniería Informática', principal: false }

function pageOf(totals: Array<{ id_carrera: number; nombre: string; total: number }>): GroupStudentsPage {
  return {
    items: [makeGroupStudent({ carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' } })],
    page: 1,
    perPage: 50,
    total: 1,
    totalPages: 1,
    enrolledTotal: 1,
    careerTotals: totals,
  }
}

function renderTable(careers: GroupCareer[], onCareerChange = vi.fn()) {
  renderWithRouter(
    <GroupStudentsTable
      searchInput=""
      onSearchChange={vi.fn()}
      query=""
      onClearSearch={vi.fn()}
      page={pageOf(careers.map((career) => ({ id_carrera: career.id_carrera, nombre: career.nombre, total: 30 })))}
      isStale={false}
      status="success"
      error={null}
      onRetry={vi.fn()}
      onPageChange={vi.fn()}
      correctingPage={false}
      careers={careers}
      onCareerChange={onCareerChange}
    />
  )

  return onCareerChange
}

describe('GroupStudentsTable — filtro por carrera (HU-021)', () => {
  it('con una sola carrera igual ofrece el filtro, sin la columna Carrera', () => {
    renderTable([sistemas])

    const filter = screen.getByRole('combobox', { name: 'Filtrar por carrera' })

    expect(filter).toHaveTextContent('Todas las carreras')
    expect(screen.getByRole('option', { name: 'Ingeniería de Sistemas (30)' })).toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Carrera' })).not.toBeInTheDocument()
  })

  it('con varias carreras ofrece el filtro y la columna, y avisa la carrera elegida', async () => {
    const onCareerChange = renderTable([sistemas, informatica])

    expect(screen.getByRole('columnheader', { name: 'Carrera' })).toBeInTheDocument()

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Filtrar por carrera' }), '2')

    expect(onCareerChange).toHaveBeenCalledWith(2)
  })

  it('sin carreras conocidas no muestra el filtro', () => {
    renderTable([])

    expect(screen.queryByRole('combobox', { name: 'Filtrar por carrera' })).not.toBeInTheDocument()
  })
})
