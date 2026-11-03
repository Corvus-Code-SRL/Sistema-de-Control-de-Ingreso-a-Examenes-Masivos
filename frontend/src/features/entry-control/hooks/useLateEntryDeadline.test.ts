import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useLateEntryDeadline } from './useLateEntryDeadline'

const END = '2026-10-31T16:00:00+00:00'

beforeEach(() => {
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useLateEntryDeadline', () => {
  it('con el fin ya pasado según el servidor termina de inmediato', () => {
    const { result } = renderHook(() => useLateEntryDeadline(END, '2026-10-31T16:05:00+00:00'))

    expect(result.current).toBe(true)
  })

  it('termina justo al llegar el fin', () => {
    const { result } = renderHook(() => useLateEntryDeadline(END, '2026-10-31T15:59:00+00:00'))
    expect(result.current).toBe(false)

    act(() => { vi.advanceTimersByTime(59_999) })
    expect(result.current).toBe(false)

    act(() => { vi.advanceTimersByTime(1) })
    expect(result.current).toBe(true)
  })

  it('cuenta con la hora del servidor, no con el reloj del equipo', () => {
    // El equipo va dos horas adelantado: con su reloj el examen ya habría terminado.
    vi.setSystemTime(new Date('2026-10-31T18:00:00+00:00'))
    const { result } = renderHook(() => useLateEntryDeadline(END, '2026-10-31T15:58:00+00:00'))

    expect(result.current).toBe(false)
    act(() => { vi.advanceTimersByTime(120_000) })
    expect(result.current).toBe(true)
  })

  it('una recarga con nueva hora del servidor vuelve a sincronizar', () => {
    const { result, rerender } = renderHook(
      ({ serverNow }) => useLateEntryDeadline(END, serverNow),
      { initialProps: { serverNow: '2026-10-31T15:50:00+00:00' } }
    )

    rerender({ serverNow: '2026-10-31T16:00:30+00:00' })
    expect(result.current).toBe(true)
  })

  it('con fechas inválidas no termina', () => {
    const { result } = renderHook(() => useLateEntryDeadline('sin fecha', 'sin fecha'))

    act(() => { vi.advanceTimersByTime(60_000) })
    expect(result.current).toBe(false)
  })
})
