import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useSubjectCareerAssignments } from './useSubjectCareerAssignments'
import { mockApiWith } from '@/test/http'

const sistemas = { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 }
const civil = { id_carrera: 4, nombre: 'Ingenieria Civil', codigo: 'CIV', id_facultad: 1 }
const materia = { id_materia: 10, nombre: 'Redes', codigo: '2008001', descripcion: null, estado: 'ACTIVO' }

const periodo1 = { id_periodo: 5, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: true }
const periodo2 = { id_periodo: 6, etiqueta: '2/2026', nombre_periodo: 'Semestre 2', gestion: 2026, vigente: false }

function offering(careerId: number, subjectId: number, period = periodo1) {
  return {
    id_carrera: careerId,
    id_materia: subjectId,
    id_periodo: period.id_periodo,
    estado: 'ACTIVO',
    carrera: careerId === 3 ? sistemas : civil,
    materia: { ...materia, id_materia: subjectId },
    periodo: period,
  }
}

const offers: Record<string, ReturnType<typeof offering>[]> = {
  '5': [offering(3, 10), offering(4, 11)],
  '6': [offering(3, 12, periodo2)],
}

function stub() {
  return mockApiWith((request) => {
    const params = new URL(request.url).searchParams
    const periodId = params.get('id_periodo') ?? ''
    const careerId = params.get('id_carrera')
    const rows = offers[periodId] ?? []

    return {
      body: {
        data: careerId ? rows.filter((row) => String(row.id_carrera) === careerId) : rows,
        meta: { carreras: [civil, sistemas], periodo: periodId === '5' ? periodo1 : periodo2 },
      },
    }
  })
}

describe('useSubjectCareerAssignments', () => {
  it('pide las ofertas del período, sin carrera trae todas', async () => {
    const api = stub()

    const { result } = renderHook(() => useSubjectCareerAssignments(5, null))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.assignments).toEqual(offers['5'])
    expect(result.current.careers).toEqual([civil, sistemas])

    const url = new URL(api.calls[0].url)

    expect(url.pathname).toContain('/administracion/asignaciones')
    expect(url.searchParams.get('id_periodo')).toBe('5')
    expect(url.searchParams.has('id_carrera')).toBe(false)
  })

  it('sin período no consulta y queda cargando', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    const { result } = renderHook(() => useSubjectCareerAssignments(null, null))

    await act(async () => undefined)

    expect(result.current.status).toBe('loading')
    expect(result.current.assignments).toEqual([])
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('con carrera pide solo las de esa carrera y oculta de inmediato las del filtro anterior', async () => {
    stub()

    const { result, rerender } = renderHook(
      ({ careerId }) => useSubjectCareerAssignments(5, careerId),
      { initialProps: { careerId: null as number | null } }
    )

    await waitFor(() => expect(result.current.status).toBe('success'))

    rerender({ careerId: 4 })

    expect(result.current.assignments).toEqual([])
    expect(result.current.status).toBe('loading')
    expect(result.current.careers).toEqual([civil, sistemas])

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.assignments).toEqual([offers['5'][1]])
  })

  it('al cambiar de período pide el nuevo y oculta de inmediato el anterior', async () => {
    const api = stub()

    const { result, rerender } = renderHook(
      ({ periodId }) => useSubjectCareerAssignments(periodId, null),
      { initialProps: { periodId: 5 } }
    )

    await waitFor(() => expect(result.current.status).toBe('success'))

    rerender({ periodId: 6 })

    expect(result.current.assignments).toEqual([])
    expect(result.current.status).toBe('loading')

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.assignments).toEqual(offers['6'])
    expect(new URL(api.calls[1].url).searchParams.get('id_periodo')).toBe('6')
  })

  it('una respuesta tardía del período anterior nunca pisa la del actual', async () => {
    const pending = new Map<string, () => void>()

    vi.stubGlobal(
      'fetch',
      vi.fn(
        (input: RequestInfo | URL) =>
          new Promise<Response>((resolve) => {
            const periodId = new URL(String(input)).searchParams.get('id_periodo') ?? ''

            pending.set(periodId, () =>
              resolve({
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({
                  data: offers[periodId],
                  meta: { carreras: [sistemas], periodo: periodId === '5' ? periodo1 : periodo2 },
                }),
              } as Response)
            )
          })
      )
    )

    const { result, rerender } = renderHook(
      ({ periodId, careerId }) => useSubjectCareerAssignments(periodId, careerId),
      { initialProps: { periodId: 5, careerId: null as number | null } }
    )

    await waitFor(() => expect(pending.has('5')).toBe(true))

    // El Administrador cambia de período antes de que llegue la primera respuesta.
    rerender({ periodId: 6, careerId: null })
    await waitFor(() => expect(pending.has('6')).toBe(true))

    await act(async () => pending.get('6')?.())
    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.assignments).toEqual(offers['6'])

    await act(async () => pending.get('5')?.())

    expect(result.current.assignments).toEqual(offers['6'])
    expect(result.current.status).toBe('success')
  })

  it('reload vuelve a pedir la lista conservando período y filtro', async () => {
    const api = stub()

    const { result } = renderHook(() => useSubjectCareerAssignments(5, 3))

    await waitFor(() => expect(result.current.status).toBe('success'))

    act(() => result.current.reload())

    await waitFor(() => expect(api.calls).toHaveLength(2))

    const url = new URL(api.calls[1].url)

    expect(url.searchParams.get('id_periodo')).toBe('5')
    expect(url.searchParams.get('id_carrera')).toBe('3')
  })

  it('expone el error del servidor', async () => {
    mockApiWith(() => ({ status: 500, body: { message: 'Error interno del servidor.' } }))

    const { result } = renderHook(() => useSubjectCareerAssignments(5, null))

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.assignments).toEqual([])
    expect(result.current.careers).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})
