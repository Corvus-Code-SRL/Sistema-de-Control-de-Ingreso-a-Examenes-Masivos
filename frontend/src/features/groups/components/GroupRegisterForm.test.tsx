import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { periodoActivo } from '@/test/fixtures'
import { useCreateGroup } from '../hooks/useCreateGroup'
import { usePeriods } from '../hooks/usePeriods'
import { RegistrarGrupoForm } from './GroupRegisterForm'

vi.mock('../hooks/useCreateGroup', () => ({ useCreateGroup: vi.fn() }))
vi.mock('../hooks/usePeriods', () => ({ usePeriods: vi.fn() }))

const mockedUseCreateGroup = vi.mocked(useCreateGroup)
const mockedUsePeriods = vi.mocked(usePeriods)

const submit = vi.fn()
const onCancel = vi.fn()
const onRegistered = vi.fn()

const periodoAnterior = { id_periodo: 2, nombre_periodo: '1-2026', gestion: 2026 }

function mockCreateGroup(overrides: Partial<ReturnType<typeof useCreateGroup>> = {}) {
  mockedUseCreateGroup.mockReturnValue({
    status: 'idle',
    error: null,
    submit,
    reset: vi.fn(),
    ...overrides,
  })
}

function renderForm() {
  return render(
    <RegistrarGrupoForm
      careerId={1}
      subjectId={10}
      subjectCareerLabel="Bases de Datos I · Ingenieria de Sistemas"
      subjectName="Bases de Datos I"
      teacherName="Paola Careaga"
      onCancel={onCancel}
      onRegistered={onRegistered}
    />
  )
}

describe('RegistrarGrupoForm (HU-018)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCreateGroup()
    mockedUsePeriods.mockReturnValue({
      periods: [periodoActivo, periodoAnterior],
      activePeriodId: periodoActivo.id_periodo,
      isLoading: false,
      error: null,
    })
  })

  it('CA1-2: muestra la materia y el docente como datos de solo lectura, y pide número y período', () => {
    renderForm()

    expect(screen.getByText('Bases de Datos I · Ingenieria de Sistemas')).toBeInTheDocument()
    expect(screen.getByText('Paola Careaga')).toBeInTheDocument()
    expect(screen.getByLabelText(/N° de grupo/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/Período académico/i)).toBeInTheDocument()
  })

  it('no ofrece el campo Auxiliares (corresponde a HU-005)', () => {
    renderForm()

    expect(screen.queryByLabelText(/Auxiliares/i)).not.toBeInTheDocument()
  })

  it('CA11: preselecciona el período activo y permite verificarlo', () => {
    renderForm()

    expect(screen.getByRole('combobox', { name: /Período académico/i })).toHaveTextContent(
      '2-2026-2026 (activo)'
    )
    expect(screen.getByText(/Por defecto, el período activo/i)).toBeInTheDocument()
  })

  it('CA3: aclara que la unicidad es por materia, carrera y período', () => {
    renderForm()

    expect(
      screen.getByText('Debe ser único dentro de la materia, la carrera y el período.')
    ).toBeInTheDocument()
    expect(screen.queryByText('Debe ser único dentro de la materia.')).not.toBeInTheDocument()
  })

  it('CA4 y CA10: sin número de grupo resalta el campo, muestra el mensaje y no envía', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('button', { name: /Registrar grupo/i }))

    const input = screen.getByLabelText(/N° de grupo/i)
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Debe indicarse el número de grupo.')).toBeInTheDocument()
    expect(screen.getByText(/Revise 1 campo antes de registrar el grupo/i)).toBeInTheDocument()
    expect(submit).not.toHaveBeenCalled()
  })

  it('limita el número de grupo a 5 caracteres', () => {
    renderForm()

    expect(screen.getByLabelText(/N° de grupo/i)).toHaveAttribute('maxLength', '5')
  })

  it('CA5 y CA6: envía el par de la materia y el período activo, y notifica el registro', async () => {
    const user = userEvent.setup()
    const registered = { grupo: { num_grupo: '7' } }
    submit.mockResolvedValue(registered)
    renderForm()

    await user.type(screen.getByLabelText(/N° de grupo/i), '7')
    await user.click(screen.getByRole('button', { name: /Registrar grupo/i }))

    expect(submit).toHaveBeenCalledWith({
      id_carrera: 1,
      id_materia: 10,
      num_grupo: '7',
      id_periodo: periodoActivo.id_periodo,
    })
    expect(onRegistered).toHaveBeenCalledWith(registered)
  })

  it('CA8 y CA10: una duplicidad devuelta por el backend se resalta en el número de grupo', () => {
    mockCreateGroup({
      status: 'error',
      error: new ApiError(422, 'The given data was invalid.', {
        num_grupo: ['Ya existe un grupo con esta identificación en la misma materia, carrera, gestión y período.'],
      }),
    })
    renderForm()

    expect(screen.getByLabelText(/N° de grupo/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText(/Ya existe un grupo con esta identificación/i)).toBeInTheDocument()
  })

  it('CA7: un par inactivo o inexistente se informa en la alerta y no en un campo', () => {
    mockCreateGroup({
      status: 'error',
      error: new ApiError(422, 'The given data was invalid.', {
        id_materia: ['La materia seleccionada no está activa en esta carrera y no puede establecerse como contexto de trabajo.'],
      }),
    })
    renderForm()

    expect(screen.getByRole('alert')).toHaveTextContent(/no está activa en esta carrera/i)
  })

  it('CA9: cancelar vuelve a la consulta sin guardar nada', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.type(screen.getByLabelText(/N° de grupo/i), '7')
    await user.click(screen.getByRole('button', { name: /Cancelar/i }))

    expect(onCancel).toHaveBeenCalledOnce()
    expect(submit).not.toHaveBeenCalled()
    expect(onRegistered).not.toHaveBeenCalled()
  })
})
