import { screen, waitForElementToBeRemoved, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ExamsPage } from './ExamsPage'
import { mockApi, mockApiOnce, mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { Exam } from '../types/exams.types'

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

function makeExam(overrides: Partial<Exam> = {}): Exam {
  return {
    id_examen: 1,
    nombre_examen: 'Primer parcial',
    fecha: '2026-10-20',
    hora_inicio: '08:00',
    hora_fin: '10:00',
    duracion: 120,
    normas: null,
    estado: 'PROGRAMADO',
    id_tipo_examen: 1,
    id_carrera: 2,
    id_materia: 3,
    id_usuario_docente: 'DOC-1', es_creador: true,
    materia: { id_materia: 3, nombre: 'Cálculo II', codigo: 'MAT-102' },
    carrera: { id_carrera: 2, nombre: 'Ingenieria de Sistemas' },
    ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 40 }],
    grupos: [{ id_grupo: 11, num_grupo: '1', gestion: '2026', estado: 'ACTIVO', id_carrera: 2, id_materia: 3, cantidad_estudiantes: 25, tiene_nomina: true }],
    ...overrides,
  }
}

describe('ExamsPage', () => {
  it('lista los examenes del docente con su estado', async () => {
    mockApiOnce({ body: { data: [makeExam()] } })

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    expect(screen.getByText('Primer parcial')).toBeInTheDocument()
    expect(screen.getByText(/Cálculo II/)).toBeInTheDocument()
    expect(screen.getByText('Programado')).toBeInTheDocument()
  })

  it('pide la vista Programados y conserva el orden que envía el servidor', async () => {
    mockApiOnce({
      body: {
        data: [
          makeExam({ id_examen: 1, nombre_examen: 'Ingreso abierto hoy', ingreso_abierto_en: '2026-10-20T07:50:00Z', control_ingreso_abierto: true }),
          makeExam({ id_examen: 2, nombre_examen: 'Programado mañana' }),
        ],
      },
    })

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    expect((fetch as unknown as { mock: { calls: unknown[][] } }).mock.calls[0][0]).toContain('vista=programados')
    const names = screen.getAllByRole('row').slice(1).map((row) => row.textContent ?? '')
    expect(names[0]).toContain('Ingreso abierto hoy')
    expect(names[1]).toContain('Programado mañana')
  })

  it('con el ingreso abierto muestra «Ingreso abierto» y el botón Controlar ingreso; sin abrir, solo Configurar', async () => {
    mockApiOnce({
      body: {
        data: [
          makeExam({ id_examen: 1, nombre_examen: 'Ingreso abierto hoy', ingreso_abierto_en: '2026-10-20T07:50:00Z', control_ingreso_abierto: true }),
          makeExam({ id_examen: 2, nombre_examen: 'Programado mañana' }),
        ],
      },
    })

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    const [open, scheduled] = screen.getAllByRole('row').slice(1)
    expect(within(open).getByText('Ingreso abierto')).toBeInTheDocument()
    expect(within(open).getByRole('button', { name: /controlar ingreso/i })).toBeInTheDocument()
    expect(within(open).getByRole('button', { name: 'Ver detalle' })).toBeInTheDocument()
    expect(within(scheduled).getByText('Programado')).toBeInTheDocument()
    expect(within(scheduled).queryByRole('button', { name: /controlar ingreso/i })).not.toBeInTheDocument()
    expect(within(scheduled).getByRole('button', { name: 'Configurar' })).toBeInTheDocument()
  })

  it('marca con «Colaborador» el examen ajeno y lo deja en solo lectura', async () => {
    mockApiOnce({
      body: {
        data: [
          makeExam({ id_examen: 1, nombre_examen: 'Propio' }),
          makeExam({ id_examen: 2, nombre_examen: 'Ajeno', es_creador: false }),
        ],
      },
    })

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    const [own, collaborating] = screen.getAllByRole('row').slice(1)
    expect(within(own).queryByText('Colaborador')).not.toBeInTheDocument()
    expect(within(own).getByRole('button', { name: 'Configurar' })).toBeInTheDocument()
    expect(within(collaborating).getByText('Colaborador')).toBeInTheDocument()
    expect(within(collaborating).queryByRole('button', { name: 'Configurar' })).not.toBeInTheDocument()
    expect(within(collaborating).getByRole('button', { name: 'Ver detalle' })).toBeInTheDocument()
  })

  it('muestra el estado vacío cuando no tiene examenes', async () => {
    mockApiOnce({ body: { data: [] } })

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    expect(screen.getByText('No tiene exámenes programados')).toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApi([{ matches: () => true, status: 500, body: { message: 'Error interno del servidor.' } }])

    renderWithRouter(<ExamsPage />, { route: '/examenes/programados' })
    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByText('Error interno del servidor.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})

it('Programados conserva su consulta general aunque la URL tenga un grupo', async () => {
  const { calls } = mockApiWith(({ url }) => url.endsWith('/examenes?vista=programados') ? { body: { data: [makeExam()] } } : undefined)
  renderWithRouter(<ExamsPage />, { route: '/examenes/programados?grupo=100' })
  expect(await screen.findByText('Primer parcial')).toBeInTheDocument()
  expect(calls).toHaveLength(1)
  expect(calls[0].url).toContain('/examenes?vista=programados')
})
