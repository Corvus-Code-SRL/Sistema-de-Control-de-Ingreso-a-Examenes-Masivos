import { act, renderHook, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { notifyInvitationsChanged, usePendingInvitationsCount } from './usePendingInvitationsCount'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('usePendingInvitationsCount', () => {
  it('cuenta las pendientes con el total que da el servidor', async () => {
    mockApiWith(({ url }) =>
      url.includes('/api/invitaciones?estado=pendientes') ? { body: { data: [], meta: { total_pendientes: 2 } } } : undefined
    )

    const { result } = renderHook(() => usePendingInvitationsCount(true))

    await waitFor(() => expect(result.current).toBe(2))
  })

  it('sin meta cuenta cero', async () => {
    const { calls } = mockApiWith(() => ({ body: { data: [] } }))

    const { result } = renderHook(() => usePendingInvitationsCount(true))

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(result.current).toBe(0)
  })

  it('no pide nada si la entrada no está visible', async () => {
    const { calls } = mockApiWith(() => ({ body: { data: [] } }))

    const { result } = renderHook(() => usePendingInvitationsCount(false))

    expect(result.current).toBe(0)
    expect(calls).toHaveLength(0)
  })

  it('vuelve a contar cuando la bandeja avisa que se respondió una invitación', async () => {
    let total = 2
    const { calls } = mockApiWith(() => ({ body: { data: [], meta: { total_pendientes: total } } }))
    const { result } = renderHook(() => usePendingInvitationsCount(true))
    await waitFor(() => expect(result.current).toBe(2))

    total = 1
    act(() => notifyInvitationsChanged())

    await waitFor(() => expect(result.current).toBe(1))
    expect(calls).toHaveLength(2)
  })
})
