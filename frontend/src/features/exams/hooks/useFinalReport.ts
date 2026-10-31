import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { finalReportService } from '../services/finalReportService'
import type { FinalReport } from '../types/finalReport.types'

/** Reporte final de un examen finalizado (HU-051). */
export function useFinalReport(examId: string | undefined) {
  const [report, setReport] = useState<FinalReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [loadError, setLoadError] = useState<ApiError | null>(null)

  const load = useCallback(
    async (signal?: AbortSignal) => {
      setLoading(true)
      setNotFound(false)
      setLoadError(null)

      if (!examId || !/^[0-9]{1,9}$/.test(examId) || Number(examId) <= 0) {
        setNotFound(true)
        setLoading(false)
        return
      }

      try {
        const data = await finalReportService.getFinalReport(Number(examId), signal)
        if (!signal?.aborted) setReport(data)
      } catch (error: unknown) {
        if (signal?.aborted) return

        if (error instanceof ApiError && error.isNotFound) {
          setNotFound(true)
        } else {
          setLoadError(
            error instanceof ApiError
              ? error
              : new ApiError(
                  0,
                  error instanceof Error ? error.message : 'No se pudo consultar el reporte final'
                )
          )
        }
      } finally {
        if (!signal?.aborted) setLoading(false)
      }
    },
    [examId]
  )

  useEffect(() => {
    const controller = new AbortController()
    void load(controller.signal)
    return () => controller.abort()
  }, [load])

  return { report, loading, notFound, loadError, reload: () => void load() }
}
