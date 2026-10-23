
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { useLocation } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { Exam, ExamStatus, Group } from '../types/exams.types'
import { EditExamPage } from './EditExamPage'

const originGroup: Group = {
  id_grupo: 100,
  num_grupo: '1',
  gestion: '2026',
  estado: 'ACTIVO',
  id_carrera: 2,
  id_materia: 3,
  cantidad_estudiantes: 20,
  tiene_nomina: true,
}

function makeExam(estado: ExamStatus, groups: Group[] = []): Exam {
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
    grupos: groups,
  }
}

function mockExam(estado: ExamStatus, groups: Group[] = []) {
  return mockApiWith(({ url, method }) => {
    if (method !== 'GET') return undefined
    if (url.endsWith('/examenes/formulario')) return {
      body: { data: { materias: [], ambientes: [], grupos: [] } },
    }
    if (url.endsWith('/examenes/9')) return { body: { data: makeExam(estado, groups) } }
  })
}

function LocationProbe() {
  const { pathname, search } = useLocation()
  return <output data-testid="location">{pathname}{search}</output>
}

function renderEdit(search = '') {
  renderWithRouter(<><EditExamPage /><LocationProbe /></>, {
    route: `/examenes/9/editar${search}`,
    path: '/examenes/:examId/*',
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

describe('EditExamPage — navegación contextual', () => {
  const origins = [
    ['?grupo=100', '/examenes/9?grupo=100'],
    ['', '/examenes/9'],
    ['?grupo=abc', '/examenes/9'],
    ['?grupo=200', '/examenes/9'],
    ['?grupo=0', '/examenes/9'],
  ]

  it.each(origins)('con %s la flecha y los breadcrumbs respetan el destino %s', async (search, destination) => {
    mockExam('PROGRAMADO', [originGroup])
    renderEdit(search)
    await screen.findByRole('button', { name: 'Guardar información general' })
    const back = screen.getByRole('link', { name: 'Volver al detalle' })
    expect(back).toHaveAttribute('href', destination)
    const breadcrumbs = within(screen.getByRole('navigation', { name: 'breadcrumb' }))
    if (search === '?grupo=100') {
      expect(breadcrumbs.getByRole('link', { name: 'Exámenes del grupo' })).toHaveAttribute('href', '/grupos/100/examenes')
      expect(breadcrumbs.getByRole('link', { name: 'Segundo parcial' })).toHaveAttribute('href', destination)
    } else {
      expect(breadcrumbs.getByRole('link', { name: 'Programados' })).toHaveAttribute('href', '/examenes/programados')
      expect(breadcrumbs.getByRole('link', { name: 'Segundo parcial' })).toHaveAttribute('href', '/examenes/9')
      expect(breadcrumbs.queryByRole('link', { name: 'Exámenes del grupo' })).not.toBeInTheDocument()
    }
    const current = breadcrumbs.getByText('Editar examen')
    expect(current).toHaveAttribute('aria-current', 'page')
    expect(current).not.toHaveAttribute('href')
    fireEvent.click(back)
    expect(screen.getByTestId('location').textContent).toBe(destination)
  })

  describe.each([0, 1])('Cancelar edición en la sección %s', (section) => {
    it.each(origins)('con %s vuelve a %s sin guardar ni cancelar el examen', async (search, destination) => {
      const { calls } = mockExam('PROGRAMADO', [originGroup])
      renderEdit(search)
      const cancelButtons = await screen.findAllByRole('button', { name: 'Cancelar edición' })
      expect(cancelButtons).toHaveLength(2)
      fireEvent.change(screen.getByLabelText('Nombre del examen *'), { target: { value: 'Cambios sin guardar' } })
      fireEvent.click(cancelButtons[section])
      expect(screen.getByTestId('location').textContent).toBe(destination)
      expect(calls.every((call) => call.method === 'GET')).toBe(true)
    })
  })
})

it('guardar la eliminación del grupo origen invalida el contexto a partir del examen actualizado', async () => {
  const otherGroup = { ...originGroup, id_grupo: 200, num_grupo: '2' }
  const { calls } = mockApiWith(({ url, method }) => {
    if (url.endsWith('/examenes/formulario')) return { body: { data: {
      materias: [{ id_carrera: 2, id_materia: 3, nombre: 'Cálculo II', codigo: 'MAT-102', carrera: 'Sistemas', es_mia: true }],
      ambientes: [],
      grupos: [originGroup, otherGroup],
    } } }
    if (url.endsWith('/examenes/9') && method === 'GET') return { body: { data: makeExam('PROGRAMADO', [originGroup, otherGroup]) } }
    if (url.endsWith('/examenes/9/grupos') && method === 'POST') return { body: { data: makeExam('PROGRAMADO', [otherGroup]) } }
  })
  renderEdit('?grupo=100')
  const groupButton = await screen.findByRole('button', { name: /Grupo 1/ })
  expect(screen.getByRole('link', { name: 'Volver al detalle' })).toHaveAttribute('href', '/examenes/9?grupo=100')
  fireEvent.click(groupButton)
  // La selección sin guardar todavía no cambia la pertenencia persistida del examen.
  expect(screen.getByRole('link', { name: 'Volver al detalle' })).toHaveAttribute('href', '/examenes/9?grupo=100')
  fireEvent.click(screen.getByRole('button', { name: 'Guardar grupos' }))
  await waitFor(() => expect(screen.getByRole('link', { name: 'Volver al detalle' })).toHaveAttribute('href', '/examenes/9'))
  const breadcrumbs = within(screen.getByRole('navigation', { name: 'breadcrumb' }))
  expect(breadcrumbs.queryByRole('link', { name: 'Exámenes del grupo' })).not.toBeInTheDocument()
  expect(breadcrumbs.getByRole('link', { name: 'Programados' })).toHaveAttribute('href', '/examenes/programados')
  expect(breadcrumbs.getByRole('link', { name: 'Segundo parcial' })).toHaveAttribute('href', '/examenes/9')
  expect(calls.filter((call) => call.method === 'POST')).toEqual([
    expect.objectContaining({ body: { grupos: [200] } }),
  ])
  expect(calls.filter((call) => call.method === 'GET')).toHaveLength(2)
  fireEvent.click(screen.getAllByRole('button', { name: 'Cancelar edición' })[0])
  expect(screen.getByTestId('location').textContent).toBe('/examenes/9')
})
