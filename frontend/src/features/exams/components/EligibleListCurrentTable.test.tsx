import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
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
      <EligibleListCurrentTable students={students} />
    )

    await user.type(screen.getByLabelText(/Buscar por nombre o código SIS/i), 'alvarez')

    // El nombre aparece dos veces: en la tabla de escritorio y en la card de móvil.
    expect(screen.getAllByText('Álvarez Rojas, Camila').length).toBeGreaterThanOrEqual(1)
    expect(screen.queryByText('Mamani Quispe, Diego')).not.toBeInTheDocument()
  })

  it('no pagina: muestra todos los estudiantes en un contenedor con scroll', () => {
    const students = Array.from({ length: 25 }, (_, i) =>
      makeStudent({ id_estudiante: i + 1, codigo_sis: String(300000000 + i), nombre_completo: `Alumno ${i}` })
    )
    render(<EligibleListCurrentTable students={students} />)

    expect(screen.getByTestId('eligible-scroll').querySelectorAll('tbody tr')).toHaveLength(25)
    expect(screen.queryByRole('button', { name: /Siguiente/i })).not.toBeInTheDocument()
  })

  it('filtra por no habilitados', async () => {
    const students = [
      makeStudent({ id_estudiante: 1 }),
      makeStudent({ id_estudiante: 2, estado_habilitacion: 'NO_HABILITADO', nombre_completo: 'Quispe, Ana' }),
    ]
    const user = userEvent.setup()
    render(<EligibleListCurrentTable students={students} />)

    await user.click(screen.getByRole('button', { name: /No habilitados · 1/ }))

    expect(screen.getAllByText('Quispe, Ana').length).toBeGreaterThan(0)
    expect(screen.queryByText('Álvarez Rojas, Camila')).not.toBeInTheDocument()
  })

  it('no muestra la columna «Motivo» aunque el servidor devuelva un motivo', () => {
    render(
      <EligibleListCurrentTable
        students={[
          makeStudent({
            estado_habilitacion: 'NO_HABILITADO',
            motivo: 'ASISTENCIA_INSUFICIENTE',
            detalle: 'Faltó a 3 clases',
          }),
        ]}
      />
    )

    expect(screen.queryByRole('columnheader', { name: 'Motivo' })).not.toBeInTheDocument()
    expect(screen.queryByText('Asistencia insuficiente')).not.toBeInTheDocument()
    expect(screen.queryByText(/Faltó a 3 clases/)).not.toBeInTheDocument()
    expect(screen.getAllByText('No habilitado').length).toBeGreaterThan(0)
  })

  it('el buscador no coincide con el motivo, solo con nombre y código SIS', async () => {
    const students = [
      makeStudent({ id_estudiante: 1, nombre_completo: 'Quispe, Ana', codigo_sis: '202240019' }),
      makeStudent({
        id_estudiante: 2,
        nombre_completo: 'Mamani, Luis',
        estado_habilitacion: 'NO_HABILITADO',
        motivo: 'ASISTENCIA_INSUFICIENTE',
      }),
    ]
    const user = userEvent.setup()
    render(<EligibleListCurrentTable students={students} />)

    await user.type(screen.getByLabelText(/Buscar por nombre o código SIS/i), 'asistencia')

    expect(screen.queryByText('Mamani, Luis')).not.toBeInTheDocument()
    expect(screen.getByText('No hay estudiantes que coincidan con los filtros.')).toBeInTheDocument()
  })

  it('no muestra casillas de selección', () => {
    render(<EligibleListCurrentTable students={[makeStudent()]} />)
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })
})
