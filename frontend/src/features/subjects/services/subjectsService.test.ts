import { describe, expect, it, vi } from 'vitest'
import {
  assignSubjectToCareer,
  getAcademicPeriods,
  getAdminCareers,
  getAdminSubjects,
  getAssignableSubjects,
  getSubjectCareerAssignments,
  getSubjectCatalog,
} from './subjectsService'
import { makeSubject, subjectCatalogResponse } from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'

describe('getSubjectCatalog', () => {
  const catalogo = Array.from({ length: 20 }, (_, index) =>
    makeSubject({ id_materia: index + 1, nombre: `Materia ${index + 1}` })
  )

  it('pide la página al servidor', async () => {
    mockApiOnce({ body: subjectCatalogResponse(catalogo) })

    await getSubjectCatalog({ page: 2, perPage: 8 })

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('page=2')
    expect(String(url)).toContain('per_page=8')
  })

  it('calcula el total de páginas a partir del total del servidor', async () => {
    mockApiOnce({ body: subjectCatalogResponse(catalogo) })

    const page = await getSubjectCatalog({ page: 1, perPage: 8 })

    expect(page.total).toBe(20)
    expect(page.totalPages).toBe(3)
    expect(page.items).toHaveLength(8)
  })

  it('entrega el tramo correspondiente a la página pedida', async () => {
    mockApiOnce({ body: subjectCatalogResponse(catalogo) })

    const page = await getSubjectCatalog({ page: 3, perPage: 8 })

    expect(page.items).toHaveLength(4)
    expect(page.items[0].nombre).toBe('Materia 17')
  })

  it('conserva el mensaje del catálogo vacío', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([], 'No hay materias disponibles en el catálogo institucional.'),
    })

    const page = await getSubjectCatalog({ page: 1, perPage: 8 })

    expect(page.items).toHaveLength(0)
    expect(page.total).toBe(0)
    expect(page.totalPages).toBe(1)
    expect(page.mensaje).toBe('No hay materias disponibles en el catálogo institucional.')
  })

  it('respeta una respuesta que ya viene paginada por el servidor', async () => {
    // Cuando el backend aplique paginate(), devolverá solo la página y un total mayor.
    mockApiOnce({
      body: {
        data: catalogo.slice(0, 8),
        meta: { total: 64, total_mias: 2, id_periodo_activo: 3 },
        mensaje: null,
      },
    })

    const page = await getSubjectCatalog({ page: 1, perPage: 8 })

    expect(page.items).toHaveLength(8)
    expect(page.total).toBe(64)
    expect(page.totalPages).toBe(8)
  })
})

describe('getAdminSubjects', () => {
  it('consulta el catálogo administrativo y devuelve las materias', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_materia: 10,
            nombre: 'Bases de Datos I',
            codigo: '2008057',
          },
          {
            id_materia: 20,
            nombre: 'Calculo II',
            codigo: '2008058',
          },
        ],
      },
    })

    const subjects = await getAdminSubjects()

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/materias/administracion')

    expect(subjects).toEqual([
      {
        id_materia: 10,
        nombre: 'Bases de Datos I',
        codigo: '2008057',
      },
      {
        id_materia: 20,
        nombre: 'Calculo II',
        codigo: '2008058',
      },
    ])
    expect(String(url)).not.toContain('q=')
  })

  it('envía el término de búsqueda sin espacios sobrantes', async () => {
    mockApiOnce({ body: { data: [] } })

    await getAdminSubjects('  cálculo  ')

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(new URL(String(url)).searchParams.get('q')).toBe('cálculo')
  })

  it('una búsqueda vacía no envía el parámetro q', async () => {
    mockApiOnce({ body: { data: [] } })

    await getAdminSubjects('   ')

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).not.toContain('q=')
  })
})

describe('getAcademicPeriods', () => {
  it('consulta /periodos y devuelve los períodos con su etiqueta', async () => {
    const periods = [
      { id_periodo: 6, etiqueta: '2/2026', nombre_periodo: 'Semestre 2', gestion: 2026, vigente: false },
      { id_periodo: 5, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: true },
    ]

    mockApiOnce({ body: { data: periods, meta: { id_periodo_activo: 5 } } })

    await expect(getAcademicPeriods()).resolves.toEqual(periods)

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/periodos')
    expect(init.method).toBe('GET')
  })
})

describe('getSubjectCareerAssignments', () => {
  const periodo = { id_periodo: 5, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: true }

  const offering = {
    id_carrera: 3,
    id_materia: 10,
    id_periodo: 5,
    estado: 'ACTIVO',
    carrera: { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 },
    materia: { id_materia: 10, nombre: 'Redes', codigo: '2008001', descripcion: null, estado: 'ACTIVO' },
    periodo,
  }

  it('consulta las ofertas del período, sin filtro de carrera', async () => {
    mockApiOnce({ body: { data: [offering], meta: { carreras: [offering.carrera], periodo } } })

    await expect(getSubjectCareerAssignments(5)).resolves.toEqual({
      assignments: [offering],
      careers: [offering.carrera],
    })

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    const params = new URL(String(url)).searchParams

    expect(String(url)).toContain('/administracion/asignaciones')
    expect(params.get('id_periodo')).toBe('5')
    expect(params.has('id_carrera')).toBe(false)
  })

  it('filtra por carrera con id_carrera, junto al período', async () => {
    mockApiOnce({ body: { data: [offering], meta: { carreras: [offering.carrera], periodo } } })

    await getSubjectCareerAssignments(5, 3)

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    const params = new URL(String(url)).searchParams

    expect(params.get('id_carrera')).toBe('3')
    expect(params.get('id_periodo')).toBe('5')
  })
})

describe('getAdminCareers', () => {
  it('consulta las carreras administrativas y devuelve la colección', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_carrera: 1,
            nombre: 'Ingenieria de Sistemas',
            codigo: 'SIS',
            id_facultad: 1,
          },
        ],
      },
    })

    const careers = await getAdminCareers()

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/administracion/carreras')
    expect(init.method).toBe('GET')
    expect(careers).toEqual([
      {
        id_carrera: 1,
        nombre: 'Ingenieria de Sistemas',
        codigo: 'SIS',
        id_facultad: 1,
      },
    ])
  })
})

describe('getAssignableSubjects', () => {
  it('consulta las materias asignables de la carrera en el período indicado', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_materia: 10,
            nombre: 'Inteligencia Artificial',
            codigo: '2008001',
            descripcion: 'Materia de prueba',
            estado: 'ACTIVO',
          },
        ],
      },
    })

    const subjects = await getAssignableSubjects(3, 5)

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/administracion/carreras/3/materias-asignables')
    expect(new URL(String(url)).searchParams.get('id_periodo')).toBe('5')
    expect(init.method).toBe('GET')
    expect(subjects[0].id_materia).toBe(10)
    expect(subjects[0].nombre).toBe('Inteligencia Artificial')
  })
})

describe('assignSubjectToCareer', () => {
  it('envia la materia y el período por POST a la carrera indicada', async () => {
    mockApiOnce({
      body: {
        data: {
          id_carrera: 3,
          id_materia: 10,
          id_periodo: 5,
          estado: 'ACTIVO',
          carrera: {
            id_carrera: 3,
            nombre: 'Ingenieria de Sistemas',
            codigo: 'SIS',
            id_facultad: 1,
          },
          materia: {
            id_materia: 10,
            nombre: 'Inteligencia Artificial',
            codigo: '2008001',
            descripcion: null,
            estado: 'ACTIVO',
          },
          periodo: { id_periodo: 5, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: true },
        },
        mensaje: 'Materia asignada a la carrera en el período correctamente.',
      },
    })

    const response = await assignSubjectToCareer(3, { id_materia: 10, id_periodo: 5 })

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/administracion/carreras/3/materias')
    expect(init.method).toBe('POST')
    expect(init.body).toBe(JSON.stringify({ id_materia: 10, id_periodo: 5 }))
    expect(response.data.id_carrera).toBe(3)
    expect(response.data.id_materia).toBe(10)
    expect(response.data.periodo.etiqueta).toBe('1/2026')
  })

  it('conserva el error 422 cuando la materia ya esta asignada en el período', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'The given data was invalid.',
        errors: {
          id_materia: ['La materia ya está asignada a la carrera en este período.'],
        },
      },
    })

    await expect(assignSubjectToCareer(3, { id_materia: 10, id_periodo: 5 })).rejects.toMatchObject({
      status: 422,
      errors: {
        id_materia: ['La materia ya está asignada a la carrera en este período.'],
      },
    })
  })
})
