import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { getAuditLogReport } from '../services/auditLogReportService'

import type { AuditLogReportFilters, AuditLogReportPage } from '../types/auditLogReport.types'

interface ReportState {
  key: string
  page: AuditLogReportPage | null
  status: 'loading' | 'success' | 'error'
  error: ApiError | null
}

/** HU-068: report requests with explicit loading and cancellation. */
export function useAuditLogReport(filters: AuditLogReportFilters) {
  const { desde, hasta, q, accion, modulo, page } = filters

  const [attempt, setAttempt] = useState(0)

  const key = JSON.stringify([desde, hasta, q, accion, modulo, page, attempt])

  const [state, setState] = useState<ReportState>({
    key: '',
    page: null,
    status: 'loading',
    error: null
  })

  const reload = useCallback(() => {
    setAttempt((previous) => previous + 1)
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setState({
      key,
      page: null,
      status: 'loading',
      error: null
    })

    getAuditLogReport({ desde, hasta, q, accion, modulo, page }, controller.signal)
      .then((result) => {
        if (!active) return

        setState({
          key,
          page: result,
          status: 'success',
          error: null
        })
      })
      .catch((cause: unknown) => {
        if (!active || controller.signal.aborted) return

        setState({
          key,
          page: null,
          status: 'error',
          error:
            cause instanceof ApiError
              ? cause
              : new ApiError(
                  0,
                  cause instanceof Error ? cause.message : 'No se pudo cargar el reporte.'
                )
        })
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [key, desde, hasta, q, accion, modulo, page])

  const current = state.key === key

  return {
    page: current ? state.page : null,
    isStale: !current,
    status: current ? state.status : ('loading' as const),
    error: current ? state.error : null,
    reload
  }
}
