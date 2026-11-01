import { fireEvent, screen, within } from '@testing-library/react'
import { useLocation } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { mockApi, mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { Exam, ExamStatus, Group } from '../types/exams.types'
import { ExamDetailPage } from './ExamDetailPage'

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
    tipo_examen: { id_tipo_examen: 1, nombre: 'Parcial', categoria: 'REGULAR' },
    materia: { id_materia: 3, nombre: 'Cálculo II', codigo: 'MAT-102' },
    carrera: { id_carrera: 2, nombre: 'Ingenieria de Sistemas' },
    ambientes: [{ id_ambiente: 5, nro_aula: '691A', capacidad: 40 }],
    grupos: groups,
  }
}

function mockExam(estado: ExamStatus, groups: Group[] = []) {
  mockApi([
    { matches: (url) => url.endsWith('/examenes/formulario'), body: { data: { materias: [], ambientes: [], grupos: [] } } },
    {
      matches: (url) => url.endsWith('/examenes/9/auxiliares'),
      body: { data: { estado, editable: false, ambientes: [], auxiliares: [] } },
    },
    { matches: (url) => url.endsWith('/examenes/9'), body: { data: makeExam(estado, groups) } },
  ])
}

async function renderDetail(route = '/examenes/9') {
  renderWithRouter(<ExamDetailPage />, {
    route,
    path: '/examenes/:examId',
  })

  await screen.findByRole('heading', {
    name: /datos del examen/i,
  })
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

  it('muestra un examen PROGRAMADO en modo consulta', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()

    expect(
      await screen.findByRole('heading', {
        name: /datos del examen/i,
      })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('button', {
        name: /editar examen/i,
      })
    ).toBeInTheDocument()

    expect(
      screen.queryByRole('button', {
        name: /guardar información general/i,
      })
    ).not.toBeInTheDocument()

    expect(
      screen.queryByRole('button', {
        name: /guardar grupos/i,
      })
    ).not.toBeInTheDocument()
  })

  it('muestra las acciones de auxiliares y ambientes cuando está PROGRAMADO', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()

    expect(
      screen.getByRole('button', { name: /habilitar auxiliares/i })
    ).toBeInTheDocument()

    expect(
      screen.getByRole('button', { name: /asignar ambientes/i })
    ).toBeInTheDocument()
  })

  it.each([
    'EN_INGRESO',
    'EN_CURSO',
    'FINALIZADO',
    'CANCELADO',
  ] as ExamStatus[])(
    'oculta las acciones de auxiliares y ambientes cuando está %s',
    async (estado) => {
      mockExam(estado)
      await renderDetail()

      expect(
        screen.queryByRole('button', { name: /habilitar auxiliares/i })
      ).not.toBeInTheDocument()

      expect(
        screen.queryByRole('button', { name: /asignar ambientes/i })
      ).not.toBeInTheDocument()
    }
  )

  it('conserva el acceso al control de ingreso', async () => {
    mockExam('EN_INGRESO')
    await renderDetail()

    expect(
      screen.getByRole('button', {
        name: /controlar ingreso/i,
      })
    ).toBeInTheDocument()
  })

  it('ubica las acciones de auxiliares en su sección', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()

    const section = screen.getByRole('region', {
      name: /personal del control de ingreso/i,
    })

    expect(
      within(section).getByRole('button', {
        name: /habilitar auxiliares/i,
      })
    ).toBeInTheDocument()

    expect(
      within(section).getByRole('button', {
        name: /asignar ambientes/i,
      })
    ).toBeInTheDocument()
  })

  it('muestra los datos del examen en una tarjeta', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()

    const section = screen.getByRole('region', {
      name: /datos del examen/i,
    })

    expect(
      within(section).getByText(/MAT-102/)
    ).toBeInTheDocument()

    expect(
      within(section).getByText(/20\/10\/2026/)
    ).toBeInTheDocument()

    expect(
      within(section).getByText(/Aula 691A \(40\)/)
    ).toBeInTheDocument()

    expect(
      within(section).getByText(/40 lugares/)
    ).toBeInTheDocument()

    expect(
      within(section).getByText(/sin grupos asignados/i)
    ).toBeInTheDocument()
  })
})

describe('ExamDetailPage — origen de navegación', () => {
  it('regresa a los exámenes del grupo y refleja ese contexto en los breadcrumbs', async () => {
    const { calls } = mockApiWith(({ url }) => {
      if (url.endsWith('/examenes/9')) return { body: { data: makeExam('FINALIZADO', [originGroup]) } }
      if (url.endsWith('/examenes/9/auxiliares')) return { body: { data: { estado: 'FINALIZADO', editable: false, ambientes: [], auxiliares: [] } } }
    })
    await renderDetail('/examenes/9?grupo=100')
    expect(screen.getByRole('link', { name: 'Volver a exámenes del grupo' })).toHaveAttribute('href', '/grupos/100/examenes')
    expect(screen.getByRole('link', { name: 'Exámenes del grupo' })).toHaveAttribute('href', '/grupos/100/examenes')
    expect(calls.some((call) => call.url.includes('grupo='))).toBe(false)
    expect(calls.some((call) => call.url.includes('/grupos/'))).toBe(false)
  })

  it('sin contexto conserva el regreso y breadcrumbs generales', async () => {
    mockExam('PROGRAMADO')
    await renderDetail()
    expect(screen.getByRole('link', { name: 'Volver a Programados' })).toHaveAttribute('href', '/examenes/programados')
    const breadcrumbs = within(screen.getByRole('navigation', { name: 'breadcrumb' }))
    expect(breadcrumbs.getByText('Exámenes')).toBeInTheDocument()
    expect(breadcrumbs.getByRole('link', { name: 'Programados' })).toHaveAttribute('href', '/examenes/programados')
  })

  it.each(['abc', '0', '-1', '2147483648'])('un grupo inválido (%s) conserva el regreso general', async (group) => {
    mockExam('PROGRAMADO')
    await renderDetail(`/examenes/9?grupo=${group}`)
    expect(screen.getByRole('link', { name: 'Volver a Programados' })).toHaveAttribute('href', '/examenes/programados')
    expect(screen.queryByRole('link', { name: 'Volver a exámenes del grupo' })).not.toBeInTheDocument()
  })
})

function LocationProbe() {
  const { pathname, search } = useLocation()
  return <output data-testid="location">{pathname}{search}</output>
}

describe('ExamDetailPage — navegación a auxiliares', () => {
  it.each([
    ['?grupo=100', '/examenes/9/auxiliares?grupo=100'],
    ['', '/examenes/9/auxiliares'],
    ['?grupo=abc', '/examenes/9/auxiliares'],
    ['?grupo=200', '/examenes/9/auxiliares'],
    ['?grupo=0', '/examenes/9/auxiliares'],
  ])('Ver auxiliares desde el detalle con %s navega a %s', async (search, destination) => {
    mockExam('FINALIZADO', [originGroup])
    renderWithRouter(<><ExamDetailPage /><LocationProbe /></>, {
      route: `/examenes/9${search}`,
      path: '/examenes/:examId/*',
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Ver auxiliares' }))
    expect(screen.getByTestId('location').textContent).toBe(destination)
  })
})

describe('ExamDetailPage — navegación a edición', () => {
  it.each([
    ['?grupo=100', '/examenes/9/editar?grupo=100'],
    ['', '/examenes/9/editar'],
    ['?grupo=abc', '/examenes/9/editar'],
    ['?grupo=200', '/examenes/9/editar'],
    ['?grupo=0', '/examenes/9/editar'],
  ])('Editar examen desde el detalle con %s navega a %s', async (search, destination) => {
    mockExam('PROGRAMADO', [originGroup])
    renderWithRouter(<><ExamDetailPage /><LocationProbe /></>, {
      route: `/examenes/9${search}`,
      path: '/examenes/:examId/*',
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Editar examen' }))
    expect(screen.getByTestId('location').textContent).toBe(destination)
  })
})

describe('ExamDetailPage — reporte final (HU-051)', () => {
  it('un examen finalizado ofrece Generar reporte y lleva al reporte final', async () => {
    mockExam('FINALIZADO')
    renderWithRouter(<><ExamDetailPage /><LocationProbe /></>, {
      route: '/examenes/9',
      path: '/examenes/:examId/*',
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Generar reporte' }))
    expect(screen.getByTestId('location').textContent).toBe('/examenes/9/reporte-final')
  })

  it.each([
    'PROGRAMADO',
    'EN_INGRESO',
    'EN_CURSO',
    'CANCELADO',
  ] as ExamStatus[])('un examen %s no ofrece Generar reporte', async (estado) => {
    mockExam(estado)
    await renderDetail()
    expect(screen.queryByRole('button', { name: 'Generar reporte' })).not.toBeInTheDocument()
  })
})

it('un grupo sintácticamente válido que no pertenece al examen usa el regreso y breadcrumbs generales', async () => {
  const { calls } = mockApiWith(({ url }) => {
    if (url.endsWith('/examenes/9')) return { body: { data: makeExam('PROGRAMADO', [{ ...originGroup, id_grupo: 200 }]) } }
    if (url.endsWith('/examenes/9/auxiliares')) return { body: { data: { estado: 'PROGRAMADO', editable: false, ambientes: [], auxiliares: [] } } }
  })
  await renderDetail('/examenes/9?grupo=100')
  expect(screen.getByRole('link', { name: 'Volver a Programados' })).toHaveAttribute('href', '/examenes/programados')
  expect(screen.queryByRole('link', { name: 'Exámenes del grupo' })).not.toBeInTheDocument()
  const breadcrumbs = within(screen.getByRole('navigation', { name: 'breadcrumb' }))
  expect(breadcrumbs.getByRole('link', { name: 'Programados' })).toHaveAttribute('href', '/examenes/programados')
  expect(calls.map((call) => new URL(call.url).pathname)).toEqual(['/api/examenes/9', '/api/examenes/9/auxiliares'])
})
