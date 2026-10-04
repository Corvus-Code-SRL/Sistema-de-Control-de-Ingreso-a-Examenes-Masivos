import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  DEFAULT_REQUEST_TIMEOUT_MS,
  UPLOAD_REQUEST_TIMEOUT_MS,
  type ApiError,
} from '@/lib/api-client'
import { confirmStudentRoster, previewStudentRoster } from './rosterService'

/**
 * Plazo que recibe cada llamada de la carga de nómina por el camino real (rosterService → api-client).
 * El preview sube un archivo (FormData) y tiene el plazo largo; el confirm envía solo un token en JSON
 * y tiene el normal.
 */
describe('rosterService — plazo de cada llamada', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  function stubHangingFetch(): void {
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (_url: unknown, init: RequestInit) =>
          new Promise((_resolve, reject) => {
            init.signal?.addEventListener('abort', () =>
              reject(new DOMException('The operation was aborted.', 'AbortError'))
            )
          })
      )
    )
  }

  async function finishedAfter(pending: Promise<unknown>, ms: number): Promise<boolean> {
    let finished = false
    void pending.then(() => (finished = true))

    await vi.advanceTimersByTimeAsync(ms)

    return finished
  }

  it('el preview (sube un archivo) espera hasta 120 s y luego falla por timeout', async () => {
    vi.useFakeTimers()
    stubHangingFetch()

    const pending = previewStudentRoster(7, new File(['x'], 'nomina.csv')).catch((e: unknown) => e)

    expect(await finishedAfter(pending, DEFAULT_REQUEST_TIMEOUT_MS)).toBe(false)
    expect(await finishedAfter(pending, UPLOAD_REQUEST_TIMEOUT_MS - DEFAULT_REQUEST_TIMEOUT_MS)).toBe(true)
    expect(((await pending) as ApiError).kind).toBe('timeout')
  })

  it('el confirm (JSON con el token) conserva el plazo normal de 15 s', async () => {
    vi.useFakeTimers()
    stubHangingFetch()

    const pending = confirmStudentRoster(7, 'token-abc').catch((e: unknown) => e)

    expect(await finishedAfter(pending, DEFAULT_REQUEST_TIMEOUT_MS)).toBe(true)
    expect(((await pending) as ApiError).kind).toBe('timeout')
  })
})
