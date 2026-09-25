import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { AssignGroupsForm } from './AssignGroupsForm'
import type { Group, SubjectCareerOption } from '../types/exams.types'

const subject: SubjectCareerOption = {
  id_carrera: 2,
  id_materia: 3,
  nombre: 'Cálculo II',
  codigo: 'MAT-202',
  carrera: 'Ingeniería de Sistemas',
  es_mia: true,
}

const groups: Group[] = [
  {
    id_grupo: 11,
    num_grupo: '1',
    gestion: '2-2026',
    estado: 'ACTIVO',
    id_carrera: 2,
    id_materia: 3,
    cantidad_estudiantes: 28,
    tiene_nomina: true,
  },
  {
    id_grupo: 12,
    num_grupo: '2',
    gestion: '2-2026',
    estado: 'ACTIVO',
    id_carrera: 2,
    id_materia: 3,
    cantidad_estudiantes: 0,
    tiene_nomina: false,
  },
]

describe('AssignGroupsForm', () => {
  it('permite alternar solamente grupos con nómina cargada', async () => {
    const user = userEvent.setup()
    const onToggleGroup = vi.fn()

    render(
      <AssignGroupsForm
        selectedSubject={subject}
        availableGroups={groups}
        selectedGroupIds={[]}
        errors={{}}
        onToggleGroup={onToggleGroup}
      />
    )

    await user.click(screen.getByRole('button', { name: /Grupo 1/i }))

    expect(onToggleGroup).toHaveBeenCalledWith(11)
    expect(screen.getByRole('button', { name: /Grupo 2/i })).toBeDisabled()
  })

  it('pide seleccionar primero el par materia-carrera', () => {
    render(
      <AssignGroupsForm
        availableGroups={[]}
        selectedGroupIds={[]}
        errors={{}}
        onToggleGroup={vi.fn()}
      />
    )

    expect(screen.getByText(/Seleccione primero una materia junto con su carrera/i)).toBeVisible()
  })
})
