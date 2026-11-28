import { act, renderHook, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { useUnassignableSubjects } from './useUnassignableSubjects'

const subject = { id_materia: 10, codigo: 'MAT-1', nombre: 'Cálculo', estado: 'ACTIVO', grupos_count: 0 }

const okBody = { materias: [subject], total: 1, page: 1, per_page: 7 }

describe('useUnassignableSubjects', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sin carrera no consulta y termina vacío', async () => {
    const { calls } = mockApiWith(() => ({ body: okBody }))

    const { result } = renderHook(() => useUnassignableSubjects(null, 2, '', 1))

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.subjects).toEqual([])
    expect(result.current.total).toBe(0)
    expect(calls).toHaveLength(0)
  })

  it('sin período tampoco consulta', async () => {
    const { calls } = mockApiWith(() => ({ body: okBody }))

    const { result } = renderHook(() => useUnassignableSubjects(1, null, '', 1))

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(calls).toHaveLength(0)
  })

  it('con carrera y período carga las materias y el total', async () => {
    const { calls } = mockApiWith(() => ({ body: { ...okBody, total: 15 } }))

    const { result } = renderHook(() => useUnassignableSubjects(1, 2, 'calc', 2))

    expect(result.current.status).toBe('loading')
    await waitFor(() => expect(result.current.status).toBe('success'))

    const url = new URL(calls[0].url)

    expect(result.current.subjects).toEqual([subject])
    expect(result.current.total).toBe(15)
    expect(result.current.perPage).toBe(7)
    expect(url.searchParams.get('buscar')).toBe('calc')
    expect(url.searchParams.get('page')).toBe('2')
  })

  it('al cambiar de página oculta lo anterior mientras llega lo nuevo', async () => {
    mockApiWith(({ url }) => {
      const page = new URL(url).searchParams.get('page')

      return {
        body: {
          materias: [{ ...subject, id_materia: Number(page), nombre: `Pagina ${page}` }],
          total: 14,
          page: Number(page),
          per_page: 7,
        },
      }
    })

    const { result, rerender } = renderHook(({ page }) => useUnassignableSubjects(1, 2, '', page), {
      initialProps: { page: 1 },
    })

    await waitFor(() => expect(result.current.subjects[0]?.nombre).toBe('Pagina 1'))

    rerender({ page: 2 })

    expect(result.current.status).toBe('loading')
    expect(result.current.subjects).toEqual([])

    await waitFor(() => expect(result.current.subjects[0]?.nombre).toBe('Pagina 2'))
    expect(result.current.status).toBe('success')
  })

  it('un error del servidor se expone como estado de error', async () => {
    mockApiWith(() => ({ status: 500, body: { message: 'Falló' } }))

    const { result } = renderHook(() => useUnassignableSubjects(1, 2, '', 1))

    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.error?.status).toBe(500)
    expect(result.current.subjects).toEqual([])
  })

  it('reload vuelve a pedir la lista', async () => {
    const { calls } = mockApiWith(() => ({ body: okBody }))

    const { result } = renderHook(() => useUnassignableSubjects(1, 2, '', 1))

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(calls).toHaveLength(1)

    act(() => result.current.reload())

    await waitFor(() => expect(calls).toHaveLength(2))
  })
})
