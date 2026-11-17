import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Clock } from 'lucide-react'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ExamStatusBadge } from './ExamStatusBadge'
import { formatExamTimer, TIMER_WARNING_SECONDS, useExamTimerRemaining } from '../hooks/useExamTimerRemaining'
import { useExamTimers } from '../hooks/useExamTimers'

export function ExamTimerCard() {
  const { timers, clock, error } = useExamTimers()
  const [selectedId, setSelectedId] = useState('')
  const exam = timers.find(({ id_examen }) => String(id_examen) === selectedId) ?? timers[0]
  const seconds = useExamTimerRemaining(exam, clock)

  if (!exam || seconds === null) return null

  return (
    <aside aria-label="Cronómetro del examen" className="fixed inset-x-3 bottom-3 z-30 mx-auto w-auto max-w-72 rounded-xl border bg-card p-3 text-card-foreground shadow-lg sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-72">
      <div className="flex items-start justify-between gap-2">
        <Link className="flex min-w-0 items-center gap-2 text-sm font-semibold hover:underline" to={`/examenes/controlar-tiempo?exam=${exam.id_examen}`}>
          <Clock className="size-4 shrink-0" aria-hidden="true" />
          <span className="truncate" title={exam.nombre_examen}>{exam.nombre_examen}</span>
        </Link>
        <ExamStatusBadge status={exam.estado} />
      </div>
      {timers.length > 1 && (
        <Select value={String(exam.id_examen)} onValueChange={setSelectedId}>
          <SelectTrigger aria-label="Cronómetro a mostrar" className="mt-2 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {timers.map((timer) => (
              <SelectItem key={timer.id_examen} value={String(timer.id_examen)}>
                {timer.nombre_examen} · {timer.fecha} {timer.hora_inicio}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
      <p role="timer" aria-label={`Tiempo restante de ${exam.nombre_examen}`} className="sciem-tnum mt-1 text-xl font-semibold">
        {formatExamTimer(seconds)}
      </p>
      <div role="status" className="text-xs text-warn-fg">
        {seconds === 0 ? 'Tiempo agotado' : seconds <= TIMER_WARNING_SECONDS ? 'Quedan 10 minutos o menos.' : null}
        {error && <p>{error}</p>}
      </div>
    </aside>
  )
}
