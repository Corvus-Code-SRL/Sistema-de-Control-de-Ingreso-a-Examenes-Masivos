import { describe, expect, it, vi } from 'vitest'
import {
  assignSubjectToCareer,
  actualizarMateria,
  getAdminCareers,
  getAdminSubjects,
  getAssignableSubjects,
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
  it('consulta las materias asignables de la carrera indicada', async () => {
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

    const subjects = await getAssignableSubjects(3)

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain(
      '/administracion/carreras/3/materias-asignables'
    )
    expect(init.method).toBe('GET')
    expect(subjects[0].id_materia).toBe(10)
    expect(subjects[0].nombre).toBe('Inteligencia Artificial')
  })
})

describe('assignSubjectToCareer', () => {
  it('envia la materia por POST a la carrera indicada', async () => {
    mockApiOnce({
      body: {
        data: {
          id_carrera: 3,
          id_materia: 10,
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
        },
        mensaje: 'Materia asignada a la carrera correctamente.',
      },
    })

    const response = await assignSubjectToCareer(3, {
      id_materia: 10,
    })

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/administracion/carreras/3/materias')
    expect(init.method).toBe('POST')
    expect(init.body).toBe(
      JSON.stringify({
        id_materia: 10,
      })
    )
    expect(response.data.id_carrera).toBe(3)
    expect(response.data.id_materia).toBe(10)
    expect(response.mensaje).toBe(
      'Materia asignada a la carrera correctamente.'
    )
  })

  it('conserva el error 422 cuando la materia ya esta asignada', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'The given data was invalid.',
        errors: {
          id_materia: [
            'La materia ya está asignada a la carrera seleccionada.',
          ],
        },
      },
    })

    await expect(
      assignSubjectToCareer(3, {
        id_materia: 10,
      })
    ).rejects.toMatchObject({
      status: 422,
      errors: {
        id_materia: [
          'La materia ya está asignada a la carrera seleccionada.',
        ],
      },
    })
  })
})

describe('actualizarMateria', () => {
  it('envia nombre y codigo por PUT a la materia indicada', async () => {
    mockApiOnce({
      body: {
        data: {
          id_materia: 10,
          nombre: 'Bases de Datos II',
          codigo: '2008058',
          descripcion: null,
          estado: 'ACTIVO',
        },
        mensaje: 'Materia actualizada correctamente.',
      },
    })

    await actualizarMateria(10, {
      nombre: 'Bases de Datos II',
      codigo: '2008058',
    })

    const [url, init] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/materias/10')
    expect(init.method).toBe('PUT')
    expect(init.body).toBe(
      JSON.stringify({
        nombre: 'Bases de Datos II',
        codigo: '2008058',
      })
    )
  })

  it('conserva los errores por campo cuando el backend responde 422', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'The given data was invalid.',
        errors: {
          codigo: ['Ya existe una materia registrada con el código 2008058.'],
        },
      },
    })

    await expect(
      actualizarMateria(10, {
        nombre: 'Bases de Datos II',
        codigo: '2008058',
      })
    ).rejects.toMatchObject({
      status: 422,
      errors: {
        codigo: ['Ya existe una materia registrada con el código 2008058.'],
      },
    })
  })
})
