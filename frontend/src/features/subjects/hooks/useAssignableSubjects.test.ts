import { renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAssignableSubjects } from './useAssignableSubjects'
import { mockApi, mockApiOnce } from '@/test/http'

describe('useAssignableSubjects', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('no consulta materias mientras no exista una carrera seleccionada', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    const { result } = renderHook(() => useAssignableSubjects(null))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual([])
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('expone las materias asignables de la carrera seleccionada', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_materia: 10,
            nombre: 'Inteligencia Artificial',
            codigo: '2008001',
            descripcion: null,
            estado: 'ACTIVO',
          },
          {
            id_materia: 20,
            nombre: 'Redes de Computadoras',
            codigo: '2008002',
            descripcion: null,
            estado: 'ACTIVO',
          },
        ],
      },
    })

    const { result } = renderHook(() => useAssignableSubjects(3))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual([
      {
        id_materia: 10,
        nombre: 'Inteligencia Artificial',
        codigo: '2008001',
        descripcion: null,
        estado: 'ACTIVO',
      },
      {
        id_materia: 20,
        nombre: 'Redes de Computadoras',
        codigo: '2008002',
        descripcion: null,
        estado: 'ACTIVO',
      },
    ])

    const [url] = (
      globalThis.fetch as ReturnType<typeof vi.fn>
    ).mock.calls[0]

    expect(String(url)).toContain(
      '/administracion/carreras/3/materias-asignables'
    )
  })

  it('vuelve a consultar cuando cambia la carrera seleccionada', async () => {
    mockApi([
      {
        matches: (url) =>
          url.includes(
            '/administracion/carreras/3/materias-asignables'
          ),
        body: {
          data: [
            {
              id_materia: 10,
              nombre: 'Inteligencia Artificial',
              codigo: '2008001',
              descripcion: null,
              estado: 'ACTIVO',
            },
          ],
        },
      },
      {
        matches: (url) =>
          url.includes(
            '/administracion/carreras/4/materias-asignables'
          ),
        body: {
          data: [
            {
              id_materia: 30,
              nombre: 'Machine Learning',
              codigo: '2008003',
              descripcion: null,
              estado: 'ACTIVO',
            },
          ],
        },
      },
    ])

    const { result, rerender } = renderHook(
      ({ careerId }: { careerId: number | null }) =>
        useAssignableSubjects(careerId),
      {
        initialProps: { careerId: 3 },
      }
    )

    await waitFor(() =>
      expect(result.current.subjects[0]?.id_materia).toBe(10)
    )

    rerender({ careerId: 4 })

    await waitFor(() =>
      expect(result.current.subjects[0]?.id_materia).toBe(30)
    )

    const urls = (
      globalThis.fetch as ReturnType<typeof vi.fn>
    ).mock.calls.map(([url]) => String(url))

    expect(
      urls.some((url) =>
        url.includes(
          '/administracion/carreras/3/materias-asignables'
        )
      )
    ).toBe(true)

    expect(
      urls.some((url) =>
        url.includes(
          '/administracion/carreras/4/materias-asignables'
        )
      )
    ).toBe(true)
  })

  it('expone el error cuando las materias asignables no pueden cargarse', async () => {
    mockApiOnce({
      status: 500,
      body: { message: 'Error interno del servidor.' },
    })

    const { result } = renderHook(() => useAssignableSubjects(3))

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.subjects).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})