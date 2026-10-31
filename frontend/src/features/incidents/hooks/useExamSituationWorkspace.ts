import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { examSituationService } from '../services/examSituationService'
import type { SituationWorkspace } from '../types/exam-situation.types'

/** Un único ciclo por pantalla: no superpone peticiones y se detiene al finalizar el examen. */
export function useExamSituationWorkspace(examId: number, roomId: number | undefined, search: string, page: number) {
  const [data, setData] = useState<SituationWorkspace | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [revision, setRevision] = useState(0)
  const [attempt, setAttempt] = useState(0)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])

  useEffect(() => {
    const controller = new AbortController()
    let timer: ReturnType<typeof setTimeout> | undefined
    let failures = 0
    setData(null)
    setError(null)

    async function refresh() {
      let delay = 5000
      try {
        const result = await examSituationService.context(examId, {
          id_ambiente: roomId, q: search || undefined, page,
        }, controller.signal)
        if (controller.signal.aborted) return
        setData(result.data)
        setError(null)
        setRevision((value) => value + 1)
        failures = 0
        if (result.data.examen.estado === 'FINALIZADO') return
      } catch (cause) {
        if (controller.signal.aborted) return
        const failure = cause instanceof ApiError ? cause : new ApiError(0, 'No se pudo actualizar la vista.')
        setError(failure.isServerError ? new ApiError(failure.status, 'No se pudo actualizar la vista.') : failure)
        if ([401, 403, 404, 409, 422].includes(failure.status)) {
          setData(null)
          return
        }
        failures += 1
        delay = Math.min(5000 * 2 ** Math.min(failures, 3), 30000)
      }
      if (!controller.signal.aborted) timer = setTimeout(() => { void refresh() }, delay)
    }

    void refresh()
    return () => {
      controller.abort()
      clearTimeout(timer)
    }
  }, [examId, roomId, search, page, attempt])

  return { data, error, revision, reload }
}