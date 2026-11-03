import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { examSituationService } from '../services/examSituationService'
import { useExamSituationWorkspace } from './useExamSituationWorkspace'
import type { SituationWorkspace } from '../types/exam-situation.types'

const workspace: SituationWorkspace = {
  examen: { id_examen: 10, nombre_examen: 'Parcial', fecha: '2026-10-29', estado: 'EN_CURSO' },
  ambientes: [], ambiente_control: null, estudiantes: [], hora_servidor: '2026-10-29T15:00:00Z',
  paginacion: { current_page: 1, last_page: 1, total: 0 },
}

beforeEach(() => { vi.useFakeTimers() })
afterEach(() => { vi.useRealTimers() })

async function flush() { await act(async () => { await Promise.resolve() }) }
async function advance(ms: number) { await act(async () => { await vi.advanceTimersByTimeAsync(ms) }) }

describe('Actualización compartida de situaciones', () => {
  it('recarga cada cinco segundos y detiene el ciclo cuando el servidor rechaza el examen con 409', async () => {
    const request = vi.spyOn(examSituationService, 'context').mockResolvedValue({ data: workspace })
    const { result } = renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    expect(result.current.revision).toBe(1)
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(2)
    request.mockRejectedValue(new ApiError(409, 'Solo se pueden registrar situaciones con el ingreso abierto o con el examen en curso.'))
    await advance(5000)
    expect(result.current.data).toBeNull()
    expect(result.current.error?.status).toBe(409)
    await advance(60000)
    expect(request).toHaveBeenCalledTimes(3)
  })

  it('sigue consultando mientras el examen está en ingreso', async () => {
    const request = vi.spyOn(examSituationService, 'context').mockResolvedValue({
      data: { ...workspace, examen: { ...workspace.examen, estado: 'EN_INGRESO' } },
    })
    const { result } = renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    await advance(10000)
    expect(result.current.data?.examen.estado).toBe('EN_INGRESO')
    expect(request).toHaveBeenCalledTimes(3)
  })

  it('aplica espera creciente ante fallos de red y limpia el error al recuperarse', async () => {
    const request = vi.spyOn(examSituationService, 'context').mockRejectedValue(new ApiError(0, 'Sin conexión'))
    const { result } = renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    expect(result.current.error?.status).toBe(0)
    await advance(9999)
    expect(request).toHaveBeenCalledTimes(1)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(2)
    request.mockResolvedValue({ data: workspace })
    await advance(20000)
    expect(request).toHaveBeenCalledTimes(3)
    expect(result.current.error).toBeNull()
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(4)
  })

  it('ante un 429 espera lo que pide Retry-After (entre 2 y 30 s) sin escalar el retroceso', async () => {
    const throttled = new ApiError(429, 'Demasiadas solicitudes', {}, undefined, { retryAfter: 12 })
    const request = vi.spyOn(examSituationService, 'context').mockRejectedValue(throttled)
    const { result } = renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    expect(result.current.error?.status).toBe(429)
    await advance(11999)
    expect(request).toHaveBeenCalledTimes(1)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(12000)
    expect(request).toHaveBeenCalledTimes(3)
    request.mockResolvedValue({ data: workspace })
    await advance(12000)
    expect(result.current.error).toBeNull()
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(5)
  })

  it('ante un 429 sin Retry-After espera cinco segundos y acota la espera a 30 s', async () => {
    const request = vi.spyOn(examSituationService, 'context')
      .mockRejectedValueOnce(new ApiError(429, 'Demasiadas solicitudes'))
      .mockRejectedValueOnce(new ApiError(429, 'Demasiadas solicitudes', {}, undefined, { retryAfter: 600 }))
      .mockResolvedValue({ data: workspace })
    renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    await advance(4999)
    expect(request).toHaveBeenCalledTimes(1)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(29999)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(3)
  })

  it.each([403, 409])('detiene el ciclo en %s y permite un reintento manual', async (status) => {
    const request = vi.spyOn(examSituationService, 'context').mockRejectedValue(new ApiError(status, 'No disponible'))
    const { result } = renderHook(() => useExamSituationWorkspace(10, undefined, '', 1))
    await flush()
    await advance(60000)
    expect(request).toHaveBeenCalledTimes(1)
    request.mockResolvedValue({ data: workspace })
    act(() => { result.current.reload() })
    await flush()
    expect(request).toHaveBeenCalledTimes(2)
    expect(result.current.error).toBeNull()
  })

  it('no superpone consultas y cancela la pendiente al desmontar', async () => {
    let resolve!: (value: { data: SituationWorkspace }) => void
    const request = vi.spyOn(examSituationService, 'context')
      .mockImplementation(() => new Promise((done) => { resolve = done }))
    const { unmount } = renderHook(() => useExamSituationWorkspace(10, 2, 'jose', 3))
    await advance(20000)
    expect(request).toHaveBeenCalledTimes(1)
    expect(request.mock.calls[0][1]).toEqual({ id_ambiente: 2, q: 'jose', page: 3 })
    const signal = request.mock.calls[0][2]
    unmount()
    expect(signal?.aborted).toBe(true)
    await act(async () => { resolve({ data: workspace }) })
    await advance(60000)
    expect(request).toHaveBeenCalledTimes(1)
  })
})