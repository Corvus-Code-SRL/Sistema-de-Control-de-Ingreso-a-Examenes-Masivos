import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useAdminSubjects } from './useAdminSubjects'
import { mockApiOnce } from '@/test/http'

const subjects = [
  { id_materia: 10, nombre: 'Bases de Datos I', codigo: '2008057', estado: 'ACTIVO' },
  { id_materia: 20, nombre: 'Calculo II', codigo: '2008058', estado: 'INACTIVO' },
]

function requestedUrls(): string[] {
  return (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.map(([url]) => String(url))
}

describe('useAdminSubjects', () => {
  it('expone las materias institucionales para administracion', async () => {
    mockApiOnce({ body: { data: subjects } })

    const { result } = renderHook(() => useAdminSubjects())

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual(subjects)
    expect(requestedUrls()[0]).not.toContain('q=')
  })

  it('pide al servidor el término de búsqueda y vuelve a consultar cuando cambia', async () => {
    mockApiOnce({ body: { data: subjects } })

    const { result, rerender } = renderHook(({ search }) => useAdminSubjects(search), {
      initialProps: { search: 'calculo' },
    })

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(requestedUrls()[0]).toContain('q=calculo')

    rerender({ search: '2008' })

    await waitFor(() => expect(requestedUrls()).toHaveLength(2))
    expect(requestedUrls()[1]).toContain('q=2008')
  })

  it('expone el error cuando el catalogo no puede cargarse', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    const { result } = renderHook(() => useAdminSubjects())

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.subjects).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})
