import { render, screen, within } from '@testing-library/react'
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
const hook = vi.hoisted(() => ({
  onSuccess: undefined as undefined | ((exam: unknown) => void),
  overrides: {} as Record<string, unknown>,
}))

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
      ...hook.overrides,
    }
  },
}))

function Where() {
  const location = useLocation()

  return <p data-testid="ubicacion">{location.pathname}</p>
}

function renderContainer(initialGroupId?: number) {
  return render(
    <MemoryRouter initialEntries={['/examenes/nuevo']}>
      <Routes>
        <Route path="/examenes/nuevo" element={<CreateExamContainer initialGroupId={initialGroupId} />} />
        <Route path="*" element={<Where />} />
      </Routes>
    </MemoryRouter>
  )
}

describe('CreateExamContainer (HU-024)', () => {
  beforeEach(() => {
    hook.onSuccess = undefined
    hook.overrides = {}
  })

  it('Cancelar lleva a Programados y no retrocede en el historial', async () => {
    renderContainer()

    await userEvent.click(screen.getByRole('button', { name: /^cancelar$/i }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/examenes/programados')
  })

  it('tras crear el examen ofrece acceder a su detalle', async () => {
    renderContainer()

    await userEvent.click(
      screen.getByRole('button', { name: /guardar examen/i })
    )

    expect(
      await screen.findByRole('dialog', {
        name: 'Examen creado correctamente',
      })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('link', { name: 'Ver detalle del examen' })
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

  it('tras crear con contexto vuelve a los exámenes del grupo y conserva el acceso al detalle', async () => {
    hook.overrides = { formData: { ...emptyForm, grupos: [100] } }
    renderContainer(100)
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))

    const back = await screen.findByRole('link', { name: 'Volver a exámenes del grupo' })
    expect(back).toHaveAttribute('href', '/grupos/100/examenes')
    expect(screen.queryByRole('link', { name: 'Ver en Programados' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver detalle del examen' })).toHaveAttribute('href', '/examenes/77?grupo=100')
    await userEvent.click(back)
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/grupos/100/examenes')
  })

  it('no propaga el grupo origen al detalle si ya no está en la selección creada', async () => {
    hook.overrides = { formData: { ...emptyForm, grupos: [200] } }
    renderContainer(100)
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))
    expect(await screen.findByRole('link', { name: 'Ver detalle del examen' })).toHaveAttribute('href', '/examenes/77')
    expect(screen.getByRole('link', { name: 'Volver a exámenes del grupo' })).toHaveAttribute('href', '/grupos/100/examenes')
  })

  it('tras crear el examen se puede seguir creando otro', async () => {
    renderContainer()
    await userEvent.click(screen.getByRole('button', { name: /guardar examen/i }))
    await userEvent.click(await screen.findByRole('button', { name: /crear otro examen/i }))

    expect(screen.getByRole('button', { name: /guardar examen/i })).toBeInTheDocument()
  })

  it('muestra la fecha exacta de apertura cuando se programa varios días antes', () => {
    hook.overrides = {
      formData: {
        ...emptyForm,
        fecha: '2026-10-20',
        hora_inicio: '08:00',
        minutos_apertura: 2880,
      },
    }
    renderContainer()

    const summary = screen.getByRole('region', { name: 'Resumen de capacidad del examen' })
    const time = within(summary).getByText('08:00')
    const date = within(summary).getByText('18/10/2026')
    expect(time.compareDocumentPosition(date)).toBe(Node.DOCUMENT_POSITION_FOLLOWING)
  })

  it('muestra estudiantes, lugares y déficit sin bloquear el guardado', () => {
    hook.overrides = {
      formData: { ...emptyForm, ambientes: [1, 2], grupos: [101] },
      options: {
        materias: [],
        ambientes: [
          { id_ambiente: 1, nro_aula: '691A', capacidad: 40 },
          { id_ambiente: 2, nro_aula: '691B', capacidad: 35 },
        ],
        grupos: [],
      },
      totalStudents: 90,
    }
    renderContainer()

    const summary = screen.getByRole('region', { name: 'Resumen de capacidad del examen' })
    expect(summary).toHaveTextContent('90 estudiantes · 75 lugares')
    const occupancy = within(summary).getByRole('progressbar', { name: 'Ocupación de ambientes' })
    expect(occupancy).toHaveAttribute('aria-valuenow', '100')
    expect(occupancy.firstElementChild).toHaveStyle({ width: '100%' })
    expect(within(summary).getByText(/Faltan 15 lugares/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /guardar examen/i })).toBeEnabled()
  })

  it('bloquea el guardado normal cuando hay advertencias pendientes', () => {
    const submitExam = vi.fn()

    hook.overrides = {
      warnings: [
        'La capacidad de los ambientes seleccionados es insuficiente.',
      ],
      submitExam,
    }

    renderContainer()

    const dialog = screen.getByRole('dialog', {
      name: 'Revise antes de confirmar',
    })

    expect(dialog).toHaveTextContent(
      'La capacidad de los ambientes seleccionados es insuficiente.'
    )

    expect(
      screen.getByRole('button', {
        name: /guardar examen/i,
        hidden: true,
      })
    ).toBeDisabled()

    expect(submitExam).not.toHaveBeenCalled()
  })

  it('solicita confirmacion explicita antes de registrar con advertencias', async () => {
    const user = userEvent.setup()
    const submitExam = vi.fn()

    hook.overrides = {
      warnings: [
        'La capacidad de los ambientes seleccionados es insuficiente.',
      ],
      submitExam,
    }

    renderContainer()

    const dialog = screen.getByRole('dialog', {
      name: 'Revise antes de confirmar',
    })

    await user.click(
      within(dialog).getByRole('button', {
        name: /crear de todos modos/i,
      })
    )

    expect(submitExam).toHaveBeenCalledExactlyOnceWith(true)
  })

  it('no muestra errores de validacion antes de intentar guardar', () => {
    hook.overrides = {
      validateForm: () => ({
        isValid: false,
        errors: {
          nombre_examen: 'El nombre del examen es obligatorio.',
        },
        warnings: {},
      }),
    }

    renderContainer()

    expect(
      screen.queryByText('El nombre del examen es obligatorio.')
    ).not.toBeInTheDocument()
  })

  it('muestra errores de validacion al intentar guardar', async () => {
    const user = userEvent.setup()
    const submitExam = vi.fn()

    hook.overrides = {
      validateForm: () => ({
        isValid: false,
        errors: {
          nombre_examen: 'El nombre del examen es obligatorio.',
        },
        warnings: {},
      }),
      submitExam,
    }

    renderContainer()

    await user.click(
      screen.getByRole('button', { name: /guardar examen/i })
    )

    expect(
      screen.getByText('El nombre del examen es obligatorio.')
    ).toBeInTheDocument()

    expect(submitExam).not.toHaveBeenCalled()
  })
})
