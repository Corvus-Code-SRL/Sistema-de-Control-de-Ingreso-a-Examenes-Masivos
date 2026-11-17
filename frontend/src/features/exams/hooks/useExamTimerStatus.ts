import { useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { throttleNotice } from '@/lib/throttle'
import { examsService } from '../services/examsService'
import { EXAM_STATUS, type Exam, type ExamStatus } from '../types/exams.types'

const STATUS_POLL_MS = 5_000
const MAX_RETRY_MS = 30_000

export function useExamTimerStatus(examId: number, active: boolean, onClosed: (exam: Exam) => void) {
  const [status, setStatus] = useState<ExamStatus | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!active) return

    const controller = new AbortController()
    let inFlight = false
    let timer: number | undefined
    let retryDelay = STATUS_POLL_MS
    let nextCheckAt = performance.now() + STATUS_POLL_MS

    function schedule() {
      window.clearTimeout(timer)
      if (controller.signal.aborted || document.hidden || inFlight) return
      timer = window.setTimeout(check, Math.max(0, Math.ceil(nextCheckAt - performance.now())))
    }

    async function check() {
      if (controller.signal.aborted || document.hidden || inFlight) return
      if (performance.now() < nextCheckAt) {
        schedule()
        return
      }

      inFlight = true
      let delay = STATUS_POLL_MS
      try {
        const exam = await examsService.getExam(examId, controller.signal)
        if (controller.signal.aborted) return
        if (exam.estado === EXAM_STATUS.FINALIZADO || exam.estado === EXAM_STATUS.CANCELADO) {
          controller.abort()
          onClosed(exam)
          return
        }

        retryDelay = STATUS_POLL_MS
        setStatus(exam.estado)
        setError(null)
      } catch (cause) {
        if (controller.signal.aborted) return

        if (cause instanceof ApiError && cause.isTooManyRequests) {
          delay = Math.max(STATUS_POLL_MS, Math.ceil(cause.retryAfter ?? 0) * 1000)
          setError(throttleNotice(delay / 1000))
        } else {
          delay = retryDelay
          retryDelay = Math.min(MAX_RETRY_MS, retryDelay * 2)
          setError('No se pudo actualizar el estado del examen. Se reintentará automáticamente.')
        }
      } finally {
        inFlight = false
        nextCheckAt = performance.now() + delay
        schedule()
      }
    }

    schedule()
    document.addEventListener('visibilitychange', schedule)
    return () => {
      controller.abort()
      window.clearTimeout(timer)
      document.removeEventListener('visibilitychange', schedule)
    }
  }, [active, examId, onClosed])

  return { status, error }
}
