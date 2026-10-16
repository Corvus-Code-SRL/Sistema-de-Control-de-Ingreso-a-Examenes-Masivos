import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAssignableSubjects } from './useAssignableSubjects'
import { mockApi, mockApiOnce } from '@/test/http'

function subject(id: number, name = 'Materia', code = '2008000') {
  return { id_materia: id, nombre: name, codigo: code, descripcion: null, estado: 'ACTIVO' }
}

function periodOf(input: RequestInfo | URL): string {
  return new URL(String(input)).searchParams.get('id_periodo') ?? ''
}

function jsonOk(data: unknown): Response {
  return { ok: true, status: 200, headers: new Headers(), json: async () => ({ data }) } as Response
}

describe('useAssignableSubjects', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('no consulta materias mientras no exista una carrera seleccionada', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    const { result } = renderHook(() => useAssignableSubjects(null, 5))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual([])
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('no consulta mientras no haya período', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    const { result } = renderHook(() => useAssignableSubjects(3, null))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual([])
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('expone las materias asignables de la carrera en el período seleccionado', async () => {
    mockApiOnce({
      body: {
        data: [
          subject(10, 'Inteligencia Artificial', '2008001'),
          subject(20, 'Redes de Computadoras', '2008002'),
        ],
      },
    })

    const { result } = renderHook(() => useAssignableSubjects(3, 5))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects.map((item) => item.id_materia)).toEqual([10, 20])

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]

    expect(String(url)).toContain('/administracion/carreras/3/materias-asignables')
    expect(periodOf(url)).toBe('5')
  })

  it('vuelve a consultar cuando cambia la carrera seleccionada', async () => {
    mockApi([
      {
        matches: (url) => url.includes('/administracion/carreras/3/materias-asignables'),
        body: { data: [subject(10)] },
      },
      {
        matches: (url) => url.includes('/administracion/carreras/4/materias-asignables'),
        body: { data: [subject(30)] },
      },
    ])

    const { result, rerender } = renderHook(
      ({ careerId }: { careerId: number | null }) => useAssignableSubjects(careerId, 5),
      { initialProps: { careerId: 3 } }
    )

    await waitFor(() => expect(result.current.subjects[0]?.id_materia).toBe(10))

    rerender({ careerId: 4 })

    expect(result.current.subjects).toEqual([])
    expect(result.current.status).toBe('loading')

    await waitFor(() => expect(result.current.subjects[0]?.id_materia).toBe(30))
  })

  it('vuelve a consultar y oculta lo anterior al cambiar de período', async () => {
    const byPeriod: Record<string, number> = { '5': 10, '6': 30 }

    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => jsonOk([subject(byPeriod[periodOf(input)])]))
    )

    const { result, rerender } = renderHook(
      ({ periodId }: { periodId: number }) => useAssignableSubjects(3, periodId),
      { initialProps: { periodId: 5 } }
    )

    await waitFor(() => expect(result.current.subjects[0]?.id_materia).toBe(10))

    rerender({ periodId: 6 })

    expect(result.current.subjects).toEqual([])
    expect(result.current.status).toBe('loading')

    await waitFor(() => expect(result.current.subjects[0]?.id_materia).toBe(30))
  })

  it('una respuesta tardía del período anterior nunca pisa la del actual', async () => {
    const pending = new Map<string, (subjectId: number) => void>()

    vi.stubGlobal(
      'fetch',
      vi.fn(
        (input: RequestInfo | URL) =>
          new Promise<Response>((resolve) => {
            pending.set(periodOf(input), (subjectId) => resolve(jsonOk([subject(subjectId)])))
          })
      )
    )

    const { result, rerender } = renderHook(
      ({ periodId }: { periodId: number }) => useAssignableSubjects(3, periodId),
      { initialProps: { periodId: 5 } }
    )

    await waitFor(() => expect(pending.has('5')).toBe(true))

    rerender({ periodId: 6 })
    await waitFor(() => expect(pending.has('6')).toBe(true))

    // La del período nuevo llega primero; la del anterior llega tarde y se descarta.
    await act(async () => pending.get('6')?.(60))
    await waitFor(() => expect(result.current.subjects[0]?.id_materia).toBe(60))

    await act(async () => pending.get('5')?.(50))

    expect(result.current.subjects[0]?.id_materia).toBe(60)
    expect(result.current.status).toBe('success')
  })

  it('expone el error cuando las materias asignables no pueden cargarse', async () => {
    mockApiOnce({
      status: 500,
      body: { message: 'Error interno del servidor.' },
    })

    const { result } = renderHook(() => useAssignableSubjects(3, 5))

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.subjects).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})
