
import { useState } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { ExamForm } from './ExamForm'
import type {
  Classroom,
  CreateExamFormData,
} from '../types/exams.types'

const classrooms: Classroom[] = [
  { id_ambiente: 1, nro_aula: '691A', capacidad: 40 },
  { id_ambiente: 2, nro_aula: '691B', capacidad: 35 },
  { id_ambiente: 3, nro_aula: '691C', capacidad: 50 },
]

const initialForm: CreateExamFormData = {
  nombre_examen: '',
  materia: null,
  categoria: 'REGULAR',
  fecha: '',
  hora_inicio: '',
  duracion: 90,
  ambientes: [],
  grupos: [],
  normas: '',
}

function ExamFormHarness({
  initialAmbientes = [],
}: {
  initialAmbientes?: number[]
}) {
  const [formData, setFormData] = useState<CreateExamFormData>({
    ...initialForm,
    ambientes: initialAmbientes,
  })

  return (
    <ExamForm
      formData={formData}
      subjects={[]}
      classrooms={classrooms}
      errors={{}}
      warnings={{}}
      updateFormData={(fields) => {
        setFormData((previous) => ({
          ...previous,
          ...fields,
        }))
      }}
    />
  )
}

describe('ExamForm - ambientes (HU-024, CA-3)', () => {
  it('permite seleccionar dos ambientes sin reemplazar el primero', async () => {
    const user = userEvent.setup()

    render(<ExamFormHarness />)

    const aulaA = screen.getByRole('checkbox', {
      name: /aula 691a/i,
    })

    const aulaB = screen.getByRole('checkbox', {
      name: /aula 691b/i,
    })

    await user.click(aulaA)
    await user.click(aulaB)

    expect(aulaA).toBeChecked()
    expect(aulaB).toBeChecked()

    expect(
      screen.getByText(/2 ambientes seleccionados/i)
    ).toBeInTheDocument()

    expect(
      screen.getByText(/capacidad total:\s*75/i)
    ).toBeInTheDocument()
  })

  it('permite deseleccionar un ambiente y recalcula el total', async () => {
    const user = userEvent.setup()

    render(<ExamFormHarness initialAmbientes={[1, 2]} />)

    const aulaA = screen.getByRole('checkbox', {
      name: /aula 691a/i,
    })

    const aulaB = screen.getByRole('checkbox', {
      name: /aula 691b/i,
    })

    await user.click(aulaB)

    expect(aulaA).toBeChecked()
    expect(aulaB).not.toBeChecked()

    expect(
      screen.getByText(/1 ambiente seleccionado/i)
    ).toBeInTheDocument()

    expect(
      screen.getByText(/capacidad total:\s*40/i)
    ).toBeInTheDocument()
  })

  it('conserva los ambientes previamente seleccionados al abrir el formulario', () => {
    render(<ExamFormHarness initialAmbientes={[2, 3]} />)

    expect(
      screen.getByRole('checkbox', { name: /aula 691a/i })
    ).not.toBeChecked()

    expect(
      screen.getByRole('checkbox', { name: /aula 691b/i })
    ).toBeChecked()

    expect(
      screen.getByRole('checkbox', { name: /aula 691c/i })
    ).toBeChecked()

    expect(
      screen.getByText(/capacidad total:\s*85/i)
    ).toBeInTheDocument()
  })
})
