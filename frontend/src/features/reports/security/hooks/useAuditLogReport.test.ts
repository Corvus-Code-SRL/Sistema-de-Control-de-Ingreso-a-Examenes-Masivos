import { act, renderHook, waitFor } from '@testing-library/react'

import { beforeEach, describe, expect, it, vi } from 'vitest'

import { getAuditLogReport } from '../services/auditLogReportService'
import { useAuditLogReport } from './useAuditLogReport'

import type { AuditLogReportFilters, AuditLogReportPage } from '../types/auditLogReport.types'

vi.mock('../services/auditLogReportService', () => ({
  getAuditLogReport: vi.fn()
}))

const request = vi.mocked(getAuditLogReport)

const FILTERS: AuditLogReportFilters = {
  desde: '2026-10-01',
  hasta: '2026-10-31',
  q: '',
  accion: null,
  modulo: null,
  page: 1
}

function response(total: number, q: string | null = null): AuditLogReportPage {
  return {
    items: [],
    totals: {
      total_operaciones: total,
      total_usuarios: total > 0 ? 1 : 0,
      por_accion: []
    },
    filters: {
      desde: FILTERS.desde,
      hasta: FILTERS.hasta,
      q,
      accion: null,
      modulo: null
    },
    canExport: total > 0,
    page: 1,
    perPage: 50,
    total,
    totalPages: 1
  }
}

function deferred<T>() {
  let resolve!: (value: T) => void

  const promise = new Promise<T>((done) => {
    resolve = done
  })

  return { promise, resolve }
}

beforeEach(() => {
  request.mockReset()
})

describe('useAuditLogReport — HU-068', () => {
  it('cancela la consulta anterior cuando cambian los filtros', async () => {
    const first = deferred<AuditLogReportPage>()

    request.mockReturnValueOnce(first.promise).mockResolvedValueOnce(response(2, 'marcelo'))

    const { result, rerender } = renderHook(({ filters }) => useAuditLogReport(filters), {
      initialProps: {
        filters: FILTERS
      }
    })

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(1)
    })

    const previousSignal = request.mock.calls[0][1]

    rerender({
      filters: {
        ...FILTERS,
        q: 'marcelo'
      }
    })

    await waitFor(() => {
      expect(result.current.status).toBe('success')
    })

    expect(previousSignal?.aborted).toBe(true)

    expect(result.current.page?.totals.total_operaciones).toBe(2)
  })

  it('ignora una respuesta antigua que llega despues de la nueva', async () => {
    const first = deferred<AuditLogReportPage>()
    const second = deferred<AuditLogReportPage>()

    request.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)

    const { result, rerender } = renderHook(({ filters }) => useAuditLogReport(filters), {
      initialProps: {
        filters: FILTERS
      }
    })

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(1)
    })

    rerender({
      filters: {
        ...FILTERS,
        q: 'marcelo'
      }
    })

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(2)
    })

    await act(async () => {
      second.resolve(response(2, 'marcelo'))
    })

    expect(result.current.page?.totals.total_operaciones).toBe(2)

    await act(async () => {
      first.resolve(response(100))
    })

    expect(result.current.status).toBe('success')

    expect(result.current.page?.totals.total_operaciones).toBe(2)

    expect(result.current.page?.filters.q).toBe('marcelo')
  })

  it('recupera el reporte al reintentar una consulta fallida', async () => {
    request
      .mockRejectedValueOnce(new Error('Error temporal de conexion.'))
      .mockResolvedValueOnce(response(5))

    const { result } = renderHook(() => useAuditLogReport(FILTERS))

    await waitFor(() => {
      expect(result.current.status).toBe('error')
    })

    expect(result.current.page).toBeNull()

    expect(result.current.error?.message).toBe('Error temporal de conexion.')

    act(() => {
      result.current.reload()
    })

    await waitFor(() => {
      expect(result.current.status).toBe('success')
    })

    expect(result.current.error).toBeNull()

    expect(result.current.page?.totals.total_operaciones).toBe(5)
  })

  it('cancela la consulta al desmontar la pantalla', async () => {
    const pending = deferred<AuditLogReportPage>()

    request.mockReturnValueOnce(pending.promise)

    const { unmount } = renderHook(() => useAuditLogReport(FILTERS))

    await waitFor(() => {
      expect(request).toHaveBeenCalledTimes(1)
    })

    const signal = request.mock.calls[0][1]

    unmount()

    expect(signal?.aborted).toBe(true)
  })
})
