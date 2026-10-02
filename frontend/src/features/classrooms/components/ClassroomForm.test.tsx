import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApiOnce } from '@/test/http'
import { ClassroomForm } from './ClassroomForm'

describe('ClassroomForm', () => {
  it('no envía el formulario con los campos obligatorios vacíos', async () => {
    const user = userEvent.setup()
    const onCreated = vi.fn()

    render(<ClassroomForm onCreated={onCreated} />)

    await user.click(screen.getByRole('button', { name: /Registrar ambiente/i }))

    expect(screen.getByText('El nombre del ambiente es obligatorio.')).toBeInTheDocument()
    expect(screen.getByText('La capacidad es obligatoria.')).toBeInTheDocument()
    expect(screen.getByText('La ubicación es obligatoria.')).toBeInTheDocument()
    expect(onCreated).not.toHaveBeenCalled()
  })

  it('no envía el formulario cuando la capacidad es menor o igual a cero', async () => {
    const user = userEvent.setup()
    const onCreated = vi.fn()

    const { container } = render(<ClassroomForm onCreated={onCreated} />)

    await user.type(screen.getByLabelText(/Nombre del ambiente/i), 'Aula 101')
    await user.type(screen.getByLabelText(/Capacidad/i), '0')
    await user.type(screen.getByLabelText(/Ubicación/i), 'Modulo A')

    // Se dispara el evento de envío directo: un clic real en el botón dispara
    // primero la validación nativa del navegador por el `min="1"` del input,
    // que nunca llega a nuestro validate() — este test cubre esa segunda capa.
    fireEvent.submit(container.querySelector('form')!)

    expect(
      screen.getByText('La capacidad debe ser un número entero mayor a cero.')
    ).toBeInTheDocument()
    expect(onCreated).not.toHaveBeenCalled()
  })

  it('muestra el error de validación que devuelve el backend', async () => {
    const user = userEvent.setup()

    mockApiOnce({
      status: 422,
      body: {
        message: 'Los datos proporcionados no son válidos.',
        errors: { nro_aula: ['Ya existe un ambiente registrado con ese nombre.'] }
      }
    })

    render(<ClassroomForm onCreated={vi.fn()} />)

    await user.type(screen.getByLabelText(/Nombre del ambiente/i), 'Aula 101')
    await user.type(screen.getByLabelText(/Capacidad/i), '30')
    await user.type(screen.getByLabelText(/Ubicación/i), 'Modulo A')

    await user.click(screen.getByRole('button', { name: /Registrar ambiente/i }))

    await waitFor(() =>
      expect(
        screen.getByText('Ya existe un ambiente registrado con ese nombre.')
      ).toBeInTheDocument()
    )
  })
})
