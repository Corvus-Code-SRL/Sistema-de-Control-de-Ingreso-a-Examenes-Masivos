import { useCallback, useEffect, useRef, useState } from 'react'
import { useAuth, type SessionUser } from '@/features/auth'
import { ApiError } from '@/lib/api-client'
import { throttleNotice } from '@/lib/throttle'
import { examsService } from '../services/examsService'
import type { ExamAttendanceDetail } from '../types/examAttendance.types'
import { EXAM_STATUS } from '../types/exams.types'

const POLL_INTERVAL_MS = 5000
const SEARCH_DELAY_MS = 300
const MAX_TIMEOUT_MS = 2 ** 31 - 1
// El limitador del detalle se comparte entre exámenes de una misma sesión.
const retryDeadline = new WeakMap<SessionUser, number>()

export function useExamAttendanceDetail(examId: number, query: string) {
  const { usuario } = useAuth()
  const [snapshot, setSnapshot] = useState<{
    query: string; detail: ExamAttendanceDetail; actor: SessionUser | null
  } | null>(null)
  const previousSearch = useRef(new URLSearchParams(query).get('q'))
  const [error, setError] = useState<ApiError | null>(null)
  const [pollNotice, setPollNotice] = useState<string | null>(null)
  const [attempt, setAttempt] = useState(0)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])

  useEffect(() => {
    const controller = new AbortController()
    let timer: ReturnType<typeof setTimeout> | undefined
    let localDeadline = 0
    const search = new URLSearchParams(query).get('q')
    const searchChanged = previousSearch.current !== search
    previousSearch.current = search
    setError(null)
    setPollNotice(null)

    function schedule(delay: number) {
      timer = setTimeout(() => { void refresh() }, Math.min(delay, MAX_TIMEOUT_MS))
    }

    async function refresh() {
      const deadline = usuario ? (retryDeadline.get(usuario) ?? 0) : localDeadline
      const remaining = deadline - performance.now()
      if (remaining > 0) {
        setPollNotice(throttleNotice(Math.ceil(remaining / 1000)))
        schedule(Math.ceil(remaining))
        return
      }

      try {
        const detail = await examsService.getAttendance(examId, query, controller.signal)
        if (controller.signal.aborted) return

        if (detail.estado !== EXAM_STATUS.EN_INGRESO && detail.estado !== EXAM_STATUS.EN_CURSO) {
          throw new ApiError(409, 'El examen no tiene el ingreso abierto ni está en curso.')
        }

        setPollNotice(null)
        setSnapshot({ query, detail, actor: usuario })
        schedule(POLL_INTERVAL_MS)
      } catch (cause) {
        if (controller.signal.aborted) return
        const failure = cause instanceof ApiError ? cause : new ApiError(0, 'No se pudo actualizar la asistencia.')

        if (failure.isTooManyRequests) {
          const delay = Math.max(POLL_INTERVAL_MS, (failure.retryAfter ?? 0) * 1000)
          localDeadline = performance.now() + delay
          if (usuario) retryDeadline.set(usuario, localDeadline)
          setPollNotice(throttleNotice(Math.ceil(delay / 1000)))
          schedule(delay)
          return
        }

        setPollNotice(null)
        setSnapshot(null)
        setError(failure)
      }
    }

    if (searchChanged) schedule(SEARCH_DELAY_MS)
    else void refresh()
    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [examId, usuario, query, attempt])

  const current = snapshot?.actor === usuario && snapshot.detail.id_examen === examId ? snapshot : null
  return {
    data: current?.detail ?? null,
    isUpdating: current !== null && current.query !== query,
    error, pollNotice, reload,
  }
}
