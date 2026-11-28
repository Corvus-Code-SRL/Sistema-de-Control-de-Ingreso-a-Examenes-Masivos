import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { mockApiWith } from '@/test/http'
import { UNASSIGN_PER_PAGE, getUnassignableSubjects, unassignSubject } from './unassignSubjectService'

const request = { careerId: 1, periodId: 2, search: '', page: 1, perPage: UNASSIGN_PER_PAGE }

describe('unassignSubjectService', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('el tamaño de página es 7', () => {
    expect(UNASSIGN_PER_PAGE).toBe(7)
  })

  it('getUnassignableSubjects pide la lista con carrera, período, página y tamaño', async () => {
    const { calls } = mockApiWith(() => ({
      body: {
        materias: [{ id_materia: 10, codigo: 'MAT-1', nombre: 'Cálculo', estado: 'ACTIVO', grupos_count: 2 }],
        total: 1,
        page: 1,
        per_page: 7,
      },
    }))

    const result = await getUnassignableSubjects(request)
    const url = new URL(calls[0].url)

    expect(calls[0].method).toBe('GET')
    expect(url.pathname.endsWith('/catalogo/desvincular-materia')).toBe(true)
    expect(url.searchParams.get('carrera')).toBe('1')
    expect(url.searchParams.get('periodo')).toBe('2')
    expect(url.searchParams.get('page')).toBe('1')
    expect(url.searchParams.get('per_page')).toBe('7')
    expect(url.searchParams.has('buscar')).toBe(false)
    expect(result.materias[0].grupos_count).toBe(2)
    expect(result.total).toBe(1)
  })

  it('envía la búsqueda recortada y omite `buscar` si está vacía', async () => {
    const { calls } = mockApiWith(() => ({ body: { materias: [], total: 0, page: 1, per_page: 7 } }))

    await getUnassignableSubjects({ ...request, search: '  simulacion  ' })
    await getUnassignableSubjects({ ...request, search: '   ' })

    expect(new URL(calls[0].url).searchParams.get('buscar')).toBe('simulacion')
    expect(new URL(calls[1].url).searchParams.has('buscar')).toBe(false)
  })

  it('normaliza una respuesta sin campos', async () => {
    mockApiWith(() => ({ body: {} }))

    expect(await getUnassignableSubjects({ ...request, page: 3 })).toEqual({
      materias: [],
      total: 0,
      page: 3,
      per_page: 7,
    })
  })

  it('un error de la API llega como ApiError con su estado', async () => {
    mockApiWith(() => ({ status: 403, body: { message: 'Sin permiso' } }))

    await expect(getUnassignableSubjects(request)).rejects.toMatchObject({ status: 403 })
  })

  it('unassignSubject envía el POST con el cuerpo y devuelve la oferta', async () => {
    const payload = { id_carrera: 1, id_periodo: 2, id_materia: 11 }
    const { calls } = mockApiWith(() => ({
      body: { ...payload, estado: 'INACTIVO', grupos_count: 0 },
    }))

    const result = await unassignSubject(payload)

    expect(calls[0].method).toBe('POST')
    expect(new URL(calls[0].url).pathname.endsWith('/catalogo/desvincular-materia')).toBe(true)
    expect(calls[0].body).toEqual(payload)
    expect(result.estado).toBe('INACTIVO')
  })

  it('un 422 conserva los errores por campo', async () => {
    mockApiWith(() => ({
      status: 422,
      body: { message: 'invalid', errors: { id_materia: ['La materia tiene 2 grupos.'] } },
    }))

    const error = await unassignSubject({ id_carrera: 1, id_periodo: 2, id_materia: 10 }).catch((e) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error.isValidation).toBe(true)
    expect(error.errors.id_materia[0]).toBe('La materia tiene 2 grupos.')
  })
})
