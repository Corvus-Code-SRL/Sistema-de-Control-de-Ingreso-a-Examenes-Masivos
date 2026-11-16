import { screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { makeGroupStudent } from '@/test/groupStudentFixtures'
import { renderWithRouter } from '@/test/render'
import type { GroupStudent } from '../types/group.types'
import { GroupStudentsTable } from './GroupStudentsTable'

function renderTable(students: GroupStudent[]) {
  renderWithRouter(
    <GroupStudentsTable
      searchInput=""
      onSearchChange={vi.fn()}
      query=""
      onClearSearch={vi.fn()}
      page={{
        items: students,
        page: 1,
        perPage: 50,
        total: students.length,
        totalPages: 1,
        enrolledTotal: students.length,
        careerTotals: []
      }}
      isStale={false}
      status="success"
      error={null}
      onRetry={vi.fn()}
      onPageChange={vi.fn()}
      correctingPage={false}
    />
  )
}

describe('GroupStudentsTable — marca Excepcional (HU-023)', () => {
  it('marca al estudiante excepcional y deja ver su motivo', () => {
    renderTable([
      makeGroupStudent({
        es_excepcional: true,
        motivo_excepcional: 'Convalidación aprobada por la Dirección de Carrera'
      })
    ])

    expect(screen.getAllByText('Excepcional').length).toBeGreaterThan(0)
    expect(
      screen.getAllByTitle('Convalidación aprobada por la Dirección de Carrera').length
    ).toBeGreaterThan(0)
  })

  it('un estudiante de la nómina no lleva la marca', () => {
    renderTable([makeGroupStudent({ es_excepcional: false, motivo_excepcional: null })])

    expect(screen.queryByText('Excepcional')).not.toBeInTheDocument()
  })
})
