import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { mockApi } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { Exam, ExamStatus } from '../types/exams.types'
import { ExamDetailPage } from './ExamDetailPage'

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
    tipo_examen: { id_tipo_examen: 1, nombre: 'Parcial', categoria: 'REGULAR' },
    materia: { id_materia: 3, nombre: 'Cálculo II', codigo: 'MAT-102' },
    carrera: { id_carrera: 2, nombre: 'Ingenieria de Sistemas' },
    ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 40 }],
    grupos: [],
  }
}

function mockExam(estado: ExamStatus) {
  mockApi([
    { matches: (url) => url.endsWith('/examenes/formulario'), body: { data: { materias: [], ambientes: [], grupos: [] } } },
    {
      matches: (url) => url.endsWith('/examenes/9/auxiliares'),
      body: { data: { estado, editable: false, ambientes: [], auxiliares: [] } },
    },
    { matches: (url) => url.endsWith('/examenes/9'), body: { data: makeExam(estado) } },
  ])
}

async function renderDetail() {
  renderWithRouter(<ExamDetailPage />, { route: '/examenes/9', path: '/examenes/:examId' })
  await waitForElementToBeRemoved(() => screen.queryAllByRole('status')[0])
}

describe('ExamDetailPage: texto de solo lectura (HU-024)', () => {
  it('un examen cancelado dice que fue cancelado, no que inició el ingreso', async () => {
    mockExam('CANCELADO')
    await renderDetail()

    expect(await screen.findByText(/fue cancelado/i)).toBeInTheDocument()
    expect(screen.queryByText(/control de ingreso de este examen ya se inició/i)).not.toBeInTheDocument()
  })

  it('un examen finalizado dice que finalizó', async () => {
    mockExam('FINALIZADO')
    await renderDetail()

    expect(await screen.findByText(/ya finalizó/i)).toBeInTheDocument()
    expect(screen.queryByText(/control de ingreso de este examen ya se inició/i)).not.toBeInTheDocument()
  })

  it('un examen en ingreso dice que el control de ingreso ya se inició', async () => {
    mockExam('EN_INGRESO')
    await renderDetail()

    expect(await screen.findByText(/control de ingreso de este examen ya se inició/i)).toBeInTheDocument()
  })

  it('un examen en curso dice que está en curso', async () => {
    mockExam('EN_CURSO')
    await renderDetail()

    expect(await screen.findByText(/está en curso/i)).toBeInTheDocument()
  })

  it('un examen programado se edita: no muestra el aviso de solo lectura', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()

    expect(await screen.findByRole('button', { name: /guardar información general/i })).toBeInTheDocument()
    expect(screen.queryByText(/quedaron fijos/i)).not.toBeInTheDocument()
  })
})
