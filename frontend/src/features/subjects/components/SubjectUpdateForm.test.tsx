import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { useUpdateSubject } from '../hooks/useUpdateSubject'
import { SubjectUpdateForm } from './SubjectUpdateForm'

vi.mock('../hooks/useUpdateSubject', () => ({
  useUpdateSubject: vi.fn(),
}))

const mockedUseUpdateSubject = vi.mocked(useUpdateSubject)

const subject = {
  id_materia: 10,
  nombre: 'Bases de Datos I',
  codigo: '2008057',
}

const submit = vi.fn()
const reset = vi.fn()

function mockHook(
  overrides: Partial<ReturnType<typeof useUpdateSubject>> = {}
) {
  mockedUseUpdateSubject.mockReturnValue({
    status: 'idle',
    error: null,
    submit,
    reset,
    ...overrides,
  })
}

describe('SubjectUpdateForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockHook()
  })

  it('precarga el nombre y el código de la materia', () => {
    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={vi.fn()}
        onUpdated={vi.fn()}
      />
    )

    expect(
      screen.getByLabelText(/Nombre de la materia/i)
    ).toHaveValue('Bases de Datos I')

    expect(
      screen.getByLabelText(/Código/i)
    ).toHaveValue('2008057')
  })

  it('no envía el formulario cuando el nombre está vacío', async () => {
    const user = userEvent.setup()

    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={vi.fn()}
        onUpdated={vi.fn()}
      />
    )

    const nombre = screen.getByLabelText(/Nombre de la materia/i)

    await user.clear(nombre)
    await user.click(
      screen.getByRole('button', { name: 'Guardar cambios' })
    )

    expect(
      screen.getByText('Debe indicarse el nombre de la materia.')
    ).toBeInTheDocument()

    expect(submit).not.toHaveBeenCalled()
  })

  it('rechaza un código que no tenga exactamente 7 dígitos', async () => {
    const user = userEvent.setup()

    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={vi.fn()}
        onUpdated={vi.fn()}
      />
    )

    const codigo = screen.getByLabelText(/Código/i)

    await user.clear(codigo)
    await user.type(codigo, '12345')

    await user.click(
      screen.getByRole('button', { name: 'Guardar cambios' })
    )

    expect(
      screen.getByText(
        'El código debe contener exactamente 7 dígitos.'
      )
    ).toBeInTheDocument()

    expect(submit).not.toHaveBeenCalled()
  })

  it('envía únicamente nombre y código y comunica la actualización', async () => {
    const user = userEvent.setup()

    const updatedSubject = {
      id_materia: 10,
      nombre: 'Bases de Datos II',
      codigo: '2008060',
      descripcion: null,
      estado: 'ACTIVO',
    }

    submit.mockResolvedValue(updatedSubject)

    const onUpdated = vi.fn()

    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={vi.fn()}
        onUpdated={onUpdated}
      />
    )

    const nombre = screen.getByLabelText(/Nombre de la materia/i)
    const codigo = screen.getByLabelText(/Código/i)

    await user.clear(nombre)
    await user.type(nombre, 'Bases de Datos II')

    await user.clear(codigo)
    await user.type(codigo, '2008060')

    await user.click(
      screen.getByRole('button', { name: 'Guardar cambios' })
    )

    expect(submit).toHaveBeenCalledWith(10, {
      nombre: 'Bases de Datos II',
      codigo: '2008060',
    })

    expect(onUpdated).toHaveBeenCalledWith(updatedSubject)
  })

  it('muestra el error del backend cuando el código ya existe', () => {
    mockHook({
      status: 'error',
      error: new ApiError(
        422,
        'Los datos proporcionados no son válidos.',
        {
          codigo: [
            'Ya existe una materia registrada con el código 2008057.',
          ],
        }
      ),
    })

    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={vi.fn()}
        onUpdated={vi.fn()}
      />
    )

    expect(
      screen.getByText(
        'Ya existe una materia registrada con el código 2008057.'
      )
    ).toBeInTheDocument()
  })

  it('cancela sin enviar ninguna actualización', async () => {
    const user = userEvent.setup()
    const onCancel = vi.fn()

    render(
      <SubjectUpdateForm
        subject={subject}
        onCancel={onCancel}
        onUpdated={vi.fn()}
      />
    )

    await user.click(
      screen.getByRole('button', { name: 'Cancelar' })
    )

    expect(onCancel).toHaveBeenCalledTimes(1)
    expect(submit).not.toHaveBeenCalled()
  })
})