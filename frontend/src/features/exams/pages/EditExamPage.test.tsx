
import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { mockApi } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { Exam, ExamStatus } from '../types/exams.types'
import { EditExamPage } from './EditExamPage'

function makeExam(estado: ExamStatus): Exam {
  return {
    id_examen: 9,
    nombre_examen: 'Segundo parcial',
    fecha: '2026-10-20',
    hora_inicio: '08:00',
    hora_fin: '09:30',
    duracion: 90,
    normas: null,
    estado,
    id_tipo_examen: 1,
    id_carrera: 2,
    id_materia: 3,
    id_usuario_docente: 'DOC-1',
    tipo_examen: {
      id_tipo_examen: 1,
      nombre: 'Parcial',
      categoria: 'REGULAR',
    },
    materia: {
      id_materia: 3,
      nombre: 'Cálculo II',
      codigo: 'MAT-102',
    },
    carrera: {
      id_carrera: 2,
      nombre: 'Ingeniería de Sistemas',
    },
    ambientes: [
      { id_ambiente: 5, nro_aula: '691A', capacidad: 40 },
    ],
    grupos: [],
  }
}

function mockExam(estado: ExamStatus) {
  mockApi([
    {
      matches: (url) => url.endsWith('/examenes/formulario'),
      body: {
        data: {
          materias: [],
          ambientes: [],
          grupos: [],
        },
      },
    },
    {
      matches: (url) => url.endsWith('/examenes/9'),
      body: { data: makeExam(estado) },
    },
  ])
}

function renderEdit() {
  renderWithRouter(<EditExamPage />, {
    route: '/examenes/9/editar',
    path: '/examenes/:examId/editar',
  })
}

describe('EditExamPage: separacion de responsabilidades', () => {
  it('permite editar un examen PROGRAMADO', async () => {
    mockExam('PROGRAMADO')
    renderEdit()

    expect(
      await screen.findByRole('button', {
        name: /guardar información general/i,
      })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('button', {
        name: /guardar grupos/i,
      })
    ).toBeInTheDocument()
  })

  it('no incluye acciones de HU-029 ni HU-030', async () => {
    mockExam('PROGRAMADO')
    renderEdit()

    await screen.findByRole('button', {
      name: /guardar información general/i,
    })

    expect(
      screen.queryByRole('button', {
        name: /habilitar auxiliares/i,
      })
    ).not.toBeInTheDocument()

    expect(
      screen.queryByRole('button', {
        name: /asignar ambientes/i,
      })
    ).not.toBeInTheDocument()

    expect(
      screen.queryByRole('region', {
        name: /gestión de auxiliares y ambientes/i,
      })
    ).not.toBeInTheDocument()
  })

  it('impide editar un examen FINALIZADO', async () => {
    mockExam('FINALIZADO')
    renderEdit()

    expect(
      await screen.findByText(/ya finalizó/i)
    ).toBeInTheDocument()

    expect(
      screen.queryByRole('button', {
        name: /guardar información general/i,
      })
    ).not.toBeInTheDocument()
  })
})
