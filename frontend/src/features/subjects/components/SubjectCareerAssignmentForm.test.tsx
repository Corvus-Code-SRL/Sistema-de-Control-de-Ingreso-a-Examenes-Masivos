import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { SubjectCareerAssignmentForm } from './SubjectCareerAssignmentForm'
import { mockApi } from '@/test/http'

beforeAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'hasPointerCapture', {
    configurable: true,
    value: () => false,
  })

  Object.defineProperty(HTMLElement.prototype, 'setPointerCapture', {
    configurable: true,
    value: () => undefined,
  })

  Object.defineProperty(HTMLElement.prototype, 'releasePointerCapture', {
    configurable: true,
    value: () => undefined,
  })

  Object.defineProperty(Element.prototype, 'scrollIntoView', {
    configurable: true,
    value: () => undefined,
  })
})

const career = {
  id_carrera: 3,
  nombre: 'Ingenieria de Sistemas',
  codigo: 'SIS',
  id_facultad: 1,
}

const subject = {
  id_materia: 10,
  nombre: 'Inteligencia Artificial',
  codigo: '2008001',
  descripcion: null,
  estado: 'ACTIVO',
}

async function selectOption(
  selectName: string,
  optionName: string
) {
  await userEvent.click(
    screen.getByRole('combobox', { name: selectName })
  )

  await userEvent.click(
    await screen.findByRole('option', { name: optionName })
  )
}

describe('SubjectCareerAssignmentForm', () => {
  it('mantiene la materia bloqueada hasta seleccionar una carrera', async () => {
    mockApi([
      {
        matches: (url) =>
          url.includes('/administracion/carreras') &&
          !url.includes('/materias-asignables'),
        body: {
          data: [career],
        },
      },
    ])

    render(<SubjectCareerAssignmentForm />)

    const careerSelect = screen.getByRole('combobox', {
      name: 'Carrera',
    })

    const subjectSelect = screen.getByRole('combobox', {
      name: 'Materia',
    })

    await waitFor(() => expect(careerSelect).toBeEnabled())

    expect(subjectSelect).toBeDisabled()
    expect(
      screen.getByRole('button', { name: 'Asignar materia' })
    ).toBeDisabled()
  })

  it('confirma y asigna una materia a la carrera seleccionada', async () => {
    let assigned = false

    mockApi([
      {
        matches: (url) =>
          url.includes('/administracion/carreras') &&
          !url.includes('/materias-asignables') &&
          !/\/carreras\/\d+\/materias$/.test(url),
        body: {
          data: [career],
        },
      },
      {
        matches: (url) =>
          url.includes(
            '/administracion/carreras/3/materias-asignables'
          ),
        get body() {
          return {
            data: assigned ? [] : [subject],
          }
        },
      },
      {
        matches: (url) =>
          /\/administracion\/carreras\/3\/materias$/.test(url),
        get body() {
          assigned = true

          return {
            data: {
              id_carrera: career.id_carrera,
              id_materia: subject.id_materia,
              estado: 'ACTIVO',
              carrera: career,
              materia: subject,
            },
            mensaje: 'Materia asignada correctamente.',
          }
        },
      },
    ])

    render(<SubjectCareerAssignmentForm />)

    await waitFor(() =>
      expect(
        screen.getByRole('combobox', { name: 'Carrera' })
      ).toBeEnabled()
    )

    await selectOption(
      'Carrera',
      'SIS · Ingenieria de Sistemas'
    )

    await waitFor(() =>
      expect(
        screen.getByRole('combobox', { name: 'Materia' })
      ).toBeEnabled()
    )

    await selectOption(
      'Materia',
      '2008001 · Inteligencia Artificial'
    )

    await userEvent.click(
      screen.getByRole('button', { name: 'Asignar materia' })
    )

    const dialog = screen.getByRole('dialog')

    expect(dialog).toBeInTheDocument()

    expect(
        within(dialog).getByRole('heading', {
            name: 'Confirmar asignación',
        })
    ).toBeInTheDocument()

    expect(
        within(dialog).getByText('SIS · Ingenieria de Sistemas')
    ).toBeInTheDocument()

    expect(
        within(dialog).getByText('2008001 · Inteligencia Artificial')
    ).toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('button', {
        name: 'Confirmar asignación',
      })
    )

    await waitFor(() =>
      expect(
        screen.getByText(
          'Inteligencia Artificial fue asignada a Ingenieria de Sistemas.'
        )
      ).toBeInTheDocument()
    )

    await waitFor(() =>
      expect(
        screen.queryByRole('dialog')
      ).not.toBeInTheDocument()
    )

    const assignmentCall = (
      globalThis.fetch as ReturnType<typeof vi.fn>
    ).mock.calls.find(([url]) =>
      /\/administracion\/carreras\/3\/materias$/.test(
        String(url)
      )
    )

    expect(assignmentCall).toBeDefined()

    const [, init] = assignmentCall!

    expect(init?.method).toBe('POST')
    expect(init?.body).toBe(
      JSON.stringify({
        id_materia: 10,
      })
    )

    await waitFor(() =>
      expect(
        screen.getByText(
          'No hay materias activas pendientes de asignación para esta carrera.'
        )
      ).toBeInTheDocument()
    )
  })

  it('muestra el error de validacion cuando el backend rechaza la asignacion', async () => {
    mockApi([
      {
        matches: (url) =>
          url.includes('/administracion/carreras') &&
          !url.includes('/materias-asignables') &&
          !/\/carreras\/\d+\/materias$/.test(url),
        body: {
          data: [career],
        },
      },
      {
        matches: (url) =>
          url.includes(
            '/administracion/carreras/3/materias-asignables'
          ),
        body: {
          data: [subject],
        },
      },
      {
        matches: (url) =>
          /\/administracion\/carreras\/3\/materias$/.test(url),
        status: 422,
        body: {
          message: 'Los datos proporcionados no son validos.',
          errors: {
            id_materia: [
              'La materia seleccionada ya se encuentra asignada a esta carrera.',
            ],
          },
        },
      },
    ])

    render(<SubjectCareerAssignmentForm />)

    await waitFor(() =>
      expect(
        screen.getByRole('combobox', { name: 'Carrera' })
      ).toBeEnabled()
    )

    await selectOption(
      'Carrera',
      'SIS · Ingenieria de Sistemas'
    )

    await waitFor(() =>
      expect(
        screen.getByRole('combobox', { name: 'Materia' })
      ).toBeEnabled()
    )

    await selectOption(
      'Materia',
      '2008001 · Inteligencia Artificial'
    )

    await userEvent.click(
      screen.getByRole('button', { name: 'Asignar materia' })
    )

    await userEvent.click(
      screen.getByRole('button', {
        name: 'Confirmar asignación',
      })
    )

    expect(
      await screen.findByText(
        'La materia seleccionada ya se encuentra asignada a esta carrera.'
      )
    ).toBeInTheDocument()

    expect(screen.getByRole('dialog')).toBeInTheDocument()

    const subjectSelect = document.getElementById('subject-select')

    expect(subjectSelect).not.toBeNull()
    expect(subjectSelect).toHaveAttribute('aria-invalid', 'true')
  })
})