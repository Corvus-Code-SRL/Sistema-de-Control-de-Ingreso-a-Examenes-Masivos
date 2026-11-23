import { renderHook, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import {
  notifyRectificationRequestsChanged,
  usePendingRectificationRequestsCount,
} from './usePendingRectificationRequestsCount'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('usePendingRectificationRequestsCount', () => {
  it('devuelve las pendientes que informa el endpoint de conteo', async () => {
    const api = mockApiWith(() => ({ body: { data: { total_pendientes: 3 } } }))
    const { result } = renderHook(() => usePendingRectificationRequestsCount(true))

    await waitFor(() => expect(result.current).toBe(3))
    expect(new URL(api.calls[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/conteo$/)
  })

  it('sin `enabled` no pide nada y cuenta cero', () => {
    const api = mockApiWith(() => ({ body: { data: { total_pendientes: 3 } } }))
    const { result } = renderHook(() => usePendingRectificationRequestsCount(false))

    expect(result.current).toBe(0)
    expect(api.calls).toHaveLength(0)
  })

  it('una respuesta sin `data` ni `meta` cuenta cero sin fallar', async () => {
    const api = mockApiWith(() => ({ body: {} }))
    const { result } = renderHook(() => usePendingRectificationRequestsCount(true))

    await waitFor(() => expect(api.calls).toHaveLength(1))
    expect(result.current).toBe(0)
  })

  it('se vuelve a pedir cuando la bandeja avisa que cambió', async () => {
    let total = 2
    const api = mockApiWith(() => ({ body: { data: { total_pendientes: total } } }))
    const { result } = renderHook(() => usePendingRectificationRequestsCount(true))

    await waitFor(() => expect(result.current).toBe(2))

    total = 1
    notifyRectificationRequestsChanged()

    await waitFor(() => expect(result.current).toBe(1))
    expect(api.calls).toHaveLength(2)
  })
})
