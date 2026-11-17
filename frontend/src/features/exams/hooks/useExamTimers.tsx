import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import { ApiError } from '@/lib/api-client'
import { examTimerService, type ExamTimer, type ExamTimerDetail } from '../services/examTimerService'
import { EXAM_STATUS } from '../types/exams.types'
import type { ExamClockSync } from '../utils/examClock'

const POLL_MS = 5_000
const IDLE_POLL_MS = 15_000
const MAX_RETRY_MS = 30_000

interface TimerSnapshot {
  exams: ExamTimer[]
  closed: ExamTimer[]
  clock: ExamClockSync | null
}

interface ExamTimersValue extends TimerSnapshot {
  timers: ExamTimer[]
  ready: boolean
  error: string | null
  accept: (detail: ExamTimerDetail) => void
}

const EMPTY_SNAPSHOT: TimerSnapshot = { exams: [], closed: [], clock: null }
const ExamTimersContext = createContext<ExamTimersValue | null>(null)

function sampleClock(serverTime: string): ExamClockSync {
  return { serverNowMs: Date.parse(serverTime), sampledMonoMs: performance.now() }
}

export function ExamTimersProvider({ children }: { children: ReactNode }) {
  const [snapshot, setSnapshot] = useState(EMPTY_SNAPSHOT)
  const [ready, setReady] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const revision = useRef(0)

  const accept = useCallback(({ examen, hora_servidor_en }: ExamTimerDetail) => {
    revision.current += 1
    const clock = sampleClock(hora_servidor_en)
    setSnapshot((previous) => {
      // Una respuesta de escritura anterior al cierre puede llegar después del sondeo.
      if (!previous.exams.some(({ id_examen }) => id_examen === examen.id_examen)) return previous

      return {
        ...previous,
        exams: previous.exams.map((exam) => exam.id_examen === examen.id_examen ? { ...exam, ...examen } : exam),
        clock,
      }
    })
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    let timeout: number | undefined
    let nextCheckAt = 0
    let inFlight = false
    let retryDelay = POLL_MS

    function schedule() {
      window.clearTimeout(timeout)
      if (controller.signal.aborted || document.hidden || inFlight) return
      timeout = window.setTimeout(refresh, Math.max(0, Math.ceil(nextCheckAt - performance.now())))
    }

    async function refresh() {
      if (controller.signal.aborted || document.hidden || inFlight) return
      inFlight = true
      const requestedRevision = revision.current
      let delay = POLL_MS

      try {
        const { examenes, hora_servidor_en } = await examTimerService.list(controller.signal)
        if (controller.signal.aborted) return
        if (requestedRevision !== revision.current) return

        const clock = sampleClock(hora_servidor_en)
        const exams = examenes.filter(({ estado }) => estado === EXAM_STATUS.EN_CURSO)
        const ids = new Set(exams.map(({ id_examen }) => id_examen))
        setSnapshot((previous) => ({
          exams,
          closed: [...previous.closed, ...previous.exams.filter(({ id_examen }) => !ids.has(id_examen))]
            .filter(({ id_examen }) => !ids.has(id_examen)),
          clock,
        }))
        setReady(true)
        setError(null)
        retryDelay = POLL_MS
        delay = exams.length > 0 ? POLL_MS : IDLE_POLL_MS
      } catch (cause) {
        if (controller.signal.aborted) return
        if (cause instanceof ApiError && (cause.isUnauthorized || cause.isForbidden)) {
          setSnapshot(EMPTY_SNAPSHOT)
          setReady(false)
        }
        if (cause instanceof ApiError && cause.isTooManyRequests) {
          delay = Math.max(POLL_MS, Math.ceil(cause.retryAfter ?? 0) * 1000)
        } else {
          delay = retryDelay
          retryDelay = Math.min(retryDelay * 2, MAX_RETRY_MS)
        }
        setError('No se pudo actualizar el estado de los cronómetros. Se reintentará automáticamente.')
      } finally {
        inFlight = false
        nextCheckAt = performance.now() + delay
        schedule()
      }
    }

    void refresh()
    document.addEventListener('visibilitychange', schedule)
    return () => {
      controller.abort()
      window.clearTimeout(timeout)
      document.removeEventListener('visibilitychange', schedule)
    }
  }, [])

  const timers = snapshot.exams.filter(({ cronometro_iniciado_en }) => cronometro_iniciado_en !== null)

  return <ExamTimersContext.Provider value={{ ...snapshot, timers, ready, error, accept }}>{children}</ExamTimersContext.Provider>
}

export function useExamTimers(): ExamTimersValue {
  const timers = useContext(ExamTimersContext)
  if (!timers) throw new Error('La vista de cronómetros requiere ExamTimersProvider.')
  return timers
}
