import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { makeGroup, periodoActivo } from '@/test/fixtures'
import { usePeriods } from '../hooks/usePeriods'
import { useUpdateGroup } from '../hooks/useUpdateGroup'
import { ActualizarGrupoForm } from './GroupUpdateForm'

vi.mock('../hooks/useUpdateGroup', () => ({ useUpdateGroup: vi.fn() }))
vi.mock('../hooks/usePeriods', () => ({ usePeriods: vi.fn() }))

const mockedUseUpdateGroup = vi.mocked(useUpdateGroup)
const mockedUsePeriods = vi.mocked(usePeriods)

const submit = vi.fn()
const onCancel = vi.fn()
const onUpdated = vi.fn()

function mockUpdateGroup(overrides: Partial<ReturnType<typeof useUpdateGroup>> = {}) {
  mockedUseUpdateGroup.mockReturnValue({
    status: 'idle',
    error: null,
    submit,
    reset: vi.fn(),
    ...overrides,
  })
}

function renderForm() {
  return render(
    <ActualizarGrupoForm
      group={makeGroup({ id_grupo: 100, num_grupo: '1' })}
      subjectCareerLabel="Bases de Datos I · Ingenieria de Sistemas"
      teacherName="Paola Careaga"
      studentCount={42}
      onCancel={onCancel}
      onUpdated={onUpdated}
    />
  )
}

describe('ActualizarGrupoForm (HU-019)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockUpdateGroup()
    mockedUsePeriods.mockReturnValue({
      periods: [periodoActivo],
      activePeriodId: periodoActivo.id_periodo,
      isLoading: false,
      error: null,
    })
  })

  it('CA2: muestra los datos actuales del grupo', () => {
    renderForm()

    expect(screen.getByLabelText(/N° de grupo/i)).toHaveValue('1')
    expect(screen.getByRole('combobox', { name: /Período académico/i })).toHaveTextContent(
      '2-2026-2026'
    )
    expect(screen.getByText('Bases de Datos I · Ingenieria de Sistemas')).toBeInTheDocument()
    expect(screen.getByText('Paola Careaga')).toBeInTheDocument()
  })

  it('CA6: muestra la materia y el docente como no editables', () => {
    renderForm()

    expect(screen.getByText('No se puede cambiar.')).toBeInTheDocument()
    expect(
      screen.queryByDisplayValue('Bases de Datos I · Ingenieria de Sistemas')
    ).not.toBeInTheDocument()
    expect(screen.getAllByRole('textbox')).toHaveLength(1)
  })

  it('usa el conteo real de inscritos, sin valores de relleno', () => {
    renderForm()

    expect(screen.getByText('Los 42 estudiantes inscritos no se modifican')).toBeInTheDocument()
    expect(screen.queryByText(/118/)).not.toBeInTheDocument()
    expect(screen.queryByText(/P\. Careaga/)).not.toBeInTheDocument()
  })

  it('CA3 y CA6: envía solo el número y el período, nunca la carrera, la materia ni el docente', async () => {
    const user = userEvent.setup()
    const updated = { grupo: { num_grupo: '9' } }
    submit.mockResolvedValue(updated)
    renderForm()

    const input = screen.getByLabelText(/N° de grupo/i)
    await user.clear(input)
    await user.type(input, '9')
    await user.click(screen.getByRole('button', { name: /Guardar cambios/i }))

    expect(submit).toHaveBeenCalledWith(100, {
      num_grupo: '9',
      id_periodo: periodoActivo.id_periodo,
    })
    expect(onUpdated).toHaveBeenCalledWith(updated)
  })

  it('CA4: con el número de grupo vacío resalta el campo y no envía', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.clear(screen.getByLabelText(/N° de grupo/i))
    await user.click(screen.getByRole('button', { name: /Guardar cambios/i }))

    expect(screen.getByLabelText(/N° de grupo/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Debe indicarse el número de grupo.')).toBeInTheDocument()
    expect(submit).not.toHaveBeenCalled()
  })

  it('CA5: una duplicidad devuelta por el backend se resalta en el número de grupo', () => {
    mockUpdateGroup({
      status: 'error',
      error: new ApiError(422, 'The given data was invalid.', {
        num_grupo: ['Ya existe un grupo con esta identificación en la misma materia, carrera, gestión y período.'],
      }),
    })
    renderForm()

    expect(screen.getByLabelText(/N° de grupo/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText(/Ya existe un grupo con esta identificación/i)).toBeInTheDocument()
  })

  it('aclara que la unicidad es por materia, carrera y período', () => {
    renderForm()

    expect(
      screen.getByText('Debe ser único dentro de la materia, la carrera y el período.')
    ).toBeInTheDocument()
  })

  it('cancelar cierra el formulario sin guardar', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('button', { name: /Cancelar/i }))

    expect(onCancel).toHaveBeenCalledOnce()
    expect(submit).not.toHaveBeenCalled()
  })
})
