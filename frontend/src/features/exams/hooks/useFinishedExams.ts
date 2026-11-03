import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { examsService } from '../services/examsService'
import type { Exam } from '../types/exams.types'

/** Exámenes finalizados del docente actual, del más reciente al más antiguo (HU-051). */
export function useFinishedExams() {
  const [exams, setExams] = useState<Exam[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<ApiError | null>(null)

  const load = useCallback(async (signal?: AbortSignal) => {
    setIsLoading(true)
    setError(null)

    try {
      const all = await examsService.listExams(signal)
      if (signal?.aborted) return

      setExams(
        all
          .filter((exam) => exam.estado === 'FINALIZADO')
          .sort(
            (a, b) => b.fecha.localeCompare(a.fecha) || b.hora_inicio.localeCompare(a.hora_inicio)
          )
      )
    } catch (cause: unknown) {
      if (signal?.aborted) return

      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError(
              0,
              cause instanceof Error ? cause.message : 'No se pudieron consultar sus exámenes'
            )
      )
    } finally {
      if (!signal?.aborted) setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void load(controller.signal)
    return () => controller.abort()
  }, [load])

  return { exams, isLoading, isEmpty: exams.length === 0, error, reload: () => void load() }
}
