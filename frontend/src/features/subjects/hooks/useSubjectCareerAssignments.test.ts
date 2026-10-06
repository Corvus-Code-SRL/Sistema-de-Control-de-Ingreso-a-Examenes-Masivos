import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useSubjectCareerAssignments } from './useSubjectCareerAssignments'
import { mockApiWith } from '@/test/http'

const sistemas = { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 }
const civil = { id_carrera: 4, nombre: 'Ingenieria Civil', codigo: 'CIV', id_facultad: 1 }
const materia = { id_materia: 10, nombre: 'Redes', codigo: '2008001', descripcion: null, estado: 'ACTIVO' }

const pairs = [
  { id_carrera: 3, id_materia: 10, estado: 'ACTIVO', carrera: sistemas, materia },
  { id_carrera: 4, id_materia: 10, estado: 'INACTIVO', carrera: civil, materia },
]

function stub() {
  return mockApiWith((request) => {
    const careerId = new URL(request.url).searchParams.get('id_carrera')

    return { body: { data: careerId ? pairs.filter((pair) => String(pair.id_carrera) === careerId) : pairs } }
  })
}

describe('useSubjectCareerAssignments', () => {
  it('sin carrera trae todos los pares', async () => {
    const api = stub()

    const { result } = renderHook(() => useSubjectCareerAssignments(null))

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.assignments).toEqual(pairs)
    expect(api.calls[0].url).toContain('/administracion/asignaciones')
    expect(api.calls[0].url).not.toContain('id_carrera')
  })

  it('con carrera pide solo los de esa carrera y oculta de inmediato los del filtro anterior', async () => {
    stub()

    const { result, rerender } = renderHook(({ careerId }) => useSubjectCareerAssignments(careerId), {
      initialProps: { careerId: null as number | null },
    })

    await waitFor(() => expect(result.current.status).toBe('success'))

    rerender({ careerId: 4 })

    expect(result.current.assignments).toEqual([])
    expect(result.current.status).toBe('loading')

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.assignments).toEqual([pairs[1]])
  })

  it('reload vuelve a pedir la lista conservando el filtro', async () => {
    const api = stub()

    const { result } = renderHook(() => useSubjectCareerAssignments(3))

    await waitFor(() => expect(result.current.status).toBe('success'))

    act(() => result.current.reload())

    await waitFor(() => expect(api.calls).toHaveLength(2))
    expect(api.calls[1].url).toContain('id_carrera=3')
  })

  it('expone el error del servidor', async () => {
    mockApiWith(() => ({ status: 500, body: { message: 'Error interno del servidor.' } }))

    const { result } = renderHook(() => useSubjectCareerAssignments(null))

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.assignments).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})
