import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { EligibleListCurrentTable } from './EligibleListCurrentTable'
import type { EligibleStudent } from '../types/eligibleList.types'

function makeStudent(overrides: Partial<EligibleStudent> = {}): EligibleStudent {
  return {
    id_estudiante: 1,
    codigo_sis: '202210443',
    nombre_completo: 'Álvarez Rojas, Camila',
    id_grupo: 2,
    nombre_grupo: 'Grupo 2',
    estado_habilitacion: 'HABILITADO',
    caso_especial: false,
    motivo: null,
    detalle: null,
    ...overrides,
  }
}

describe('EligibleListCurrentTable', () => {
  it('busca sin tildes por nombre', async () => {
    const students = [
      makeStudent({ id_estudiante: 1, nombre_completo: 'Álvarez Rojas, Camila' }),
      makeStudent({ id_estudiante: 2, nombre_completo: 'Mamani Quispe, Diego', codigo_sis: '202240019' }),
    ]
    const user = userEvent.setup()
    render(
      <EligibleListCurrentTable
        students={students}
        selectedIds={new Set()}
        onSelectionChange={vi.fn()}
        editable
      />
    )

    await user.type(screen.getByLabelText(/Buscar por nombre o código SIS/i), 'alvarez')

    expect(screen.getByText('Álvarez Rojas, Camila')).toBeInTheDocument()
    expect(screen.queryByText('Mamani Quispe, Diego')).not.toBeInTheDocument()
  })

  it('marca todos los estudiantes no especiales', async () => {
    const students = [
      makeStudent({ id_estudiante: 1 }),
      makeStudent({ id_estudiante: 2, caso_especial: true, estado_habilitacion: 'CASO_ESPECIAL' }),
    ]
    const onSelectionChange = vi.fn()
    const user = userEvent.setup()
    render(
      <EligibleListCurrentTable
        students={students}
        selectedIds={new Set()}
        onSelectionChange={onSelectionChange}
        editable
      />
    )

    await user.click(screen.getByLabelText(/Marcar todos/i))
    expect(onSelectionChange).toHaveBeenCalledWith(new Set([1]))
  })

  it('no muestra checkboxes si no es editable', () => {
    render(
      <EligibleListCurrentTable
        students={[makeStudent()]}
        selectedIds={new Set()}
        onSelectionChange={vi.fn()}
        editable={false}
      />
    )
    expect(screen.queryByLabelText(/Marcar todos/i)).not.toBeInTheDocument()
  })
})
