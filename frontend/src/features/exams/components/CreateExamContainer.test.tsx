import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import type { CreateExamFormData, Exam } from '../types/exams.types'
import { CreateExamContainer } from './CreateExamContainer'

const createdExam: Exam = {
  id_examen: 77,
  nombre_examen: 'Primer parcial',
  fecha: '2026-10-20',
  hora_inicio: '08:00',
  hora_fin: '09:30',
  duracion: 90,
  normas: null,
  estado: 'PROGRAMADO',
  id_tipo_examen: 1,
  id_carrera: 2,
  id_materia: 3,
  id_usuario_docente: 'DOC-1',
}

const emptyForm: CreateExamFormData = {
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

/** `submitExam` simula una creación exitosa: entrega el examen al callback que pasó el contenedor. */
const hook = vi.hoisted(() => ({ onSuccess: undefined as undefined | ((exam: unknown) => void) }))

vi.mock('../hooks/useCreateExam', () => ({
  useCreateExam: (onSuccess: (exam: unknown) => void) => {
    hook.onSuccess = onSuccess

    return {
      formData: emptyForm,
      options: { materias: [], ambientes: [], grupos: [] },
      subjectGroups: [],
      loading: false,
      submitting: false,
      apiError: null,
      warnings: [],
      updateFormData: vi.fn(),
      resetFormData: vi.fn(),
      validateForm: () => ({ isValid: true, errors: {}, warnings: {} }),
      toggleGroup: vi.fn(),
      submitExam: () => hook.onSuccess?.(createdExam),
    }
  },
}))

function Where() {
  const location = useLocation()

  return <p data-testid="ubicacion">{location.pathname}</p>
}

function renderContainer() {
  return render(
    <MemoryRouter initialEntries={['/examenes/nuevo']}>
      <Routes>
        <Route path="/examenes/nuevo" element={<CreateExamContainer />} />
        <Route path="*" element={<Where />} />
      </Routes>
    </MemoryRouter>
  )
}

describe('CreateExamContainer (HU-024)', () => {
  beforeEach(() => {
    hook.onSuccess = undefined
  })

  it('Cancelar lleva a Programados y no retrocede en el historial', async () => {
    renderContainer()

    await userEvent.click(screen.getByRole('button', { name: /^cancelar$/i }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/examenes/programados')
  })

  it('tras crear el examen ofrece configurar auxiliares y ambientes en su detalle', async () => {
    renderContainer()
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))

    expect(await screen.findByText('Examen creado')).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Configurar auxiliares y ambientes' })
    ).toHaveAttribute('href', '/examenes/77')
  })

  it('tras crear el examen ofrece verlo en Programados', async () => {
    renderContainer()
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))

    expect(await screen.findByRole('link', { name: 'Ver en Programados' })).toHaveAttribute(
      'href',
      '/examenes/programados'
    )
  })

  it('tras crear el examen se puede seguir creando otro', async () => {
    renderContainer()
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))
    await userEvent.click(await screen.findByRole('button', { name: /crear otro examen/i }))

    expect(screen.getByRole('button', { name: /guardar examen/i })).toBeInTheDocument()
  })
})
