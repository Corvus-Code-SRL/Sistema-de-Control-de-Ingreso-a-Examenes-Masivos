import { describe, expect, it, vi } from 'vitest'
import {
  actualizarMateria,
  getAdminSubjects,
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
