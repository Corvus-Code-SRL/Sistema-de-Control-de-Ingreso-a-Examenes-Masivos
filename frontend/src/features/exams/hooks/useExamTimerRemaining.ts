import { useEffect, useReducer } from 'react'
import type { ExamTimer } from '../services/examTimerService'
import type { ExamClockSync } from '../utils/examClock'

export const TIMER_WARNING_SECONDS = 10 * 60

export function formatExamTimer(seconds: number) {
  if (seconds === 0) return '00:00'

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return [hours, minutes, seconds % 60].map((part) => String(part).padStart(2, '0')).join(':')
}

export function useExamTimerRemaining(timer: ExamTimer | undefined, clock: ExamClockSync | null) {
  const [, renderTick] = useReducer((tick: number) => tick + 1, 0)
  const storedEnd = Date.parse(timer?.cronometro_vence_en ?? '')
  const officialEnd = Date.parse(timer?.fin_permitido_en ?? '')
  const endsAt = Math.min(storedEnd, officialEnd)
  const deadline = clock ? clock.sampledMonoMs + (endsAt - clock.serverNowMs) : NaN

  useEffect(() => {
    if (!Number.isFinite(deadline)) return

    let timeout: number | undefined
    function schedule() {
      window.clearTimeout(timeout)
      const ms = deadline - performance.now()
      if (ms <= 0) return
      timeout = window.setTimeout(update, Math.ceil(ms - (Math.ceil(ms / 1000) - 1) * 1000))
    }
    function update() {
      renderTick()
      schedule()
    }

    schedule()
    document.addEventListener('visibilitychange', update)
    return () => {
      window.clearTimeout(timeout)
      document.removeEventListener('visibilitychange', update)
    }
  }, [deadline])

  return Number.isFinite(deadline) ? Math.max(0, Math.ceil((deadline - performance.now()) / 1000)) : null
}
