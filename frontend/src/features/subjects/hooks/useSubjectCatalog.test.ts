import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useSubjectCatalog } from './useSubjectCatalog'
import { makeSubject, subjectCatalogResponse } from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'

describe('useSubjectCatalog', () => {
  it('empieza cargando y no reporta error ni vacío', async () => {
    mockApiOnce({ body: subjectCatalogResponse([makeSubject()]) })

    const { result } = renderHook(() => useSubjectCatalog())

    expect(result.current.isLoading).toBe(true)
    expect(result.current.isEmpty).toBe(false)
    expect(result.current.error).toBeNull()

    await waitFor(() => expect(result.current.isLoading).toBe(false))
  })

  it('expone las materias de la página', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([
        makeSubject({ id_materia: 1, nombre: 'Bases de Datos I' }),
        makeSubject({ id_materia: 2, nombre: 'Calculo II', es_mia: false, cantidad_grupos: 0 }),
      ]),
    })

    const { result } = renderHook(() => useSubjectCatalog())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.subjects).toHaveLength(2)
    expect(result.current.isEmpty).toBe(false)
    expect(result.current.error).toBeNull()
  })

  it('distingue el catálogo vacío de un fallo', async () => {
    mockApiOnce({
      body: subjectCatalogResponse([], 'No hay materias disponibles en el catálogo institucional.'),
    })

    const { result } = renderHook(() => useSubjectCatalog())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isEmpty).toBe(true)
    expect(result.current.error).toBeNull()
    expect(result.current.subjects).toHaveLength(0)
  })

  it('expone el error del servidor sin marcarlo como vacío', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    const { result } = renderHook(() => useSubjectCatalog())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.error?.message).toBe('Error interno del servidor.')
    expect(result.current.isEmpty).toBe(false)
  })

  it('vuelve a consultar al cambiar de página', async () => {
    mockApiOnce({ body: subjectCatalogResponse([makeSubject()]) })

    const { result } = renderHook(() => useSubjectCatalog())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    act(() => result.current.goToPage(2))

    await waitFor(() => expect(result.current.currentPage).toBe(2))

    const urls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.map(([url]) =>
      String(url)
    )

    expect(urls.some((url) => url.includes('page=2'))).toBe(true)
  })

  it('nunca baja de la primera página', async () => {
    mockApiOnce({ body: subjectCatalogResponse([makeSubject()]) })

    const { result } = renderHook(() => useSubjectCatalog())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    act(() => result.current.goToPage(0))

    await waitFor(() => expect(result.current.currentPage).toBe(1))
  })
})
