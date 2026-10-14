import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ExamForm } from './ExamForm'
import type { CreateExamFormData } from '../types/exams.types'

beforeAll(() => {
  Element.prototype.hasPointerCapture = vi.fn(() => false)
  Element.prototype.setPointerCapture = vi.fn()
  Element.prototype.releasePointerCapture = vi.fn()
  Element.prototype.scrollIntoView = vi.fn()
})

const formData: CreateExamFormData = {
  nombre_examen: 'Parcial',
  materia: null,
  categoria: 'REGULAR',
  fecha: '2099-01-20',
  hora_inicio: '08:15',
  duracion: 90,
  ambientes: [],
  grupos: [],
  normas: '',
}

function renderForm(overrides: Partial<CreateExamFormData> = {}) {
  const updateFormData = vi.fn()
  render(
    <ExamForm
      formData={{ ...formData, ...overrides }}
      subjects={[]}
      classrooms={[]}
      errors={{}}
      warnings={{}}
      updateFormData={updateFormData}
    />
  )
  return updateFormData
}

describe('ExamForm: fecha y hora', () => {
  it('muestra los valores existentes en español y en formato de 24 horas', () => {
    renderForm()
    expect(screen.getByRole('button', { name: /Fecha/i })).toHaveTextContent('20 de enero de 2099')
    expect(screen.getByRole('button', { name: /Hora/i })).toHaveTextContent('08:15')
  })

  it('selecciona una fecha del calendario y conserva el formato local para la API', async () => {
    const user = userEvent.setup()
    const updateFormData = renderForm()
    await user.click(screen.getByRole('button', { name: /Fecha/i }))
    const calendar = screen.getByRole('grid')
    await user.click(within(calendar).getByRole('button', { name: /21 de enero de 2099/i }))
    expect(updateFormData).toHaveBeenCalledWith({ fecha: '2099-01-21' })
    expect(screen.queryByRole('grid')).not.toBeInTheDocument()
  })

  it('no permite elegir días anteriores a hoy', async () => {
    const user = userEvent.setup()
    renderForm({ fecha: '' })
    await user.click(screen.getByRole('button', { name: /Fecha/i }))

    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    const label = new Intl.DateTimeFormat('es-BO', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(yesterday)
    expect(within(screen.getByRole('grid')).getByRole('button', { name: new RegExp(label, 'i') })).toBeDisabled()
  })

  it('cambia los minutos sin perder la hora y permite precisión de un minuto', async () => {
    const user = userEvent.setup()
    const updateFormData = renderForm()
    await user.click(screen.getByRole('button', { name: /Hora/i }))
    await user.click(screen.getByRole('option', { name: '37' }))
    expect(updateFormData).toHaveBeenCalledWith({ hora_inicio: '08:37' })
  })
})
