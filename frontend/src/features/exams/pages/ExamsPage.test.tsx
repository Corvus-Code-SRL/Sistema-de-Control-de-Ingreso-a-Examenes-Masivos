import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ExamsPage } from './ExamsPage'
import { mockApi, mockApiOnce } from '@/test/http'
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
    id_usuario_docente: 'DOC-1',
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
