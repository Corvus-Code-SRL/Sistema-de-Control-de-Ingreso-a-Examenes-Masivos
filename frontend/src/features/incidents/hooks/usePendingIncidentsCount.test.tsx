import { renderHook, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiOnce, mockApiWith } from '@/test/http'
import { usePendingIncidentsCount } from './usePendingIncidentsCount'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('usePendingIncidentsCount', () => {
  it('devuelve el total de pendientes que informa el servidor', async () => {
    mockApiOnce({ body: { data: [], meta: { total_pendientes: 3, total_revisadas: 1 } } })

    const { result } = renderHook(() => usePendingIncidentsCount(true))

    await waitFor(() => expect(result.current).toBe(3))
  })

  it('no rompe y devuelve 0 si la respuesta no trae meta', async () => {
    mockApiOnce({ body: { data: [] } })

    const { result } = renderHook(() => usePendingIncidentsCount(true))

    await waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(result.current).toBe(0)
  })

  it('no pide nada cuando no está habilitado', () => {
    const { calls } = mockApiWith(() => undefined)

    const { result } = renderHook(() => usePendingIncidentsCount(false))

    expect(result.current).toBe(0)
    expect(calls).toHaveLength(0)
  })
})
