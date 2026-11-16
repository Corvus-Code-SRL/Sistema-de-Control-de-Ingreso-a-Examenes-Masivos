import { useEffect, useState } from 'react'
import { Clock } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { examsService } from '../services/examsService'
import { EXAM_STATUS, type Exam, type ExamStatus } from '../types/exams.types'

const TITLE = 'Controlar tiempo del examen'
const WARNING_SECONDS = 10 * 60
const AVAILABLE_STATES: ReadonlySet<ExamStatus> = new Set([
  EXAM_STATUS.PROGRAMADO,
  EXAM_STATUS.EN_CURSO,
])

function formatDate(value: string) {
  return value?.split('-').reverse().join('/') || '—'
}

function formatCountdown(seconds: number) {
  if (seconds === 0) return '00:00'

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)

  return [hours, minutes, seconds % 60].map((part) => String(part).padStart(2, '0')).join(':')
}

function ExamCountdown({ exam }: { exam: Exam }) {
  const duration = exam.duracion
  const hasDuration = Number.isInteger(duration) && duration > 0
  const [deadline, setDeadline] = useState<number | null>(null)
  const [remaining, setRemaining] = useState(hasDuration ? duration * 60 : 0)

  useEffect(() => {
    if (deadline === null) return

    const update = () => {
      const seconds = Math.max(0, Math.ceil((deadline - performance.now()) / 1000))
      setRemaining(seconds)
      if (seconds === 0) window.clearInterval(interval)
    }

    const interval = window.setInterval(update, 1000)
    document.addEventListener('visibilitychange', update)

    return () => {
      window.clearInterval(interval)
      document.removeEventListener('visibilitychange', update)
    }
  }, [deadline])

  const started = deadline !== null
  const notice = started && remaining <= WARNING_SECONDS
    ? (remaining === 0 ? 'Tiempo agotado' : 'Quedan 10 minutos o menos.')
    : null

  return (
    <section aria-labelledby="selected-exam-title" className="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <h2 id="selected-exam-title" className="sciem-h2 break-words">{exam.nombre_examen}</h2>
          <p className="text-sm text-muted-foreground">
            {[exam.materia?.nombre, exam.carrera?.nombre].filter(Boolean).join(' · ') || 'Examen'}
          </p>
        </div>
        <ExamStatusBadge status={exam.estado} />
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <dl className="space-y-3 rounded-lg border p-4 text-sm">
          <div>
            <dt className="text-muted-foreground">Fecha</dt>
            <dd className="sciem-tnum font-medium">{formatDate(exam.fecha)}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Horario programado</dt>
            <dd className="sciem-tnum font-medium">
              {exam.hora_inicio && exam.hora_fin ? `${exam.hora_inicio}–${exam.hora_fin}` : '—'}
            </dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Duración prevista</dt>
            <dd className="sciem-tnum font-medium">{hasDuration ? `${duration} min` : 'Sin duración registrada'}</dd>
          </div>
        </dl>

        <div className="rounded-lg bg-sunken p-4">
          <p id="exam-time-remaining" className="text-sm text-muted-foreground">Tiempo restante</p>
          <p role="timer" aria-labelledby="exam-time-remaining" className="sciem-tnum mt-1 text-3xl font-semibold">
            {hasDuration ? formatCountdown(remaining) : '—'}
          </p>
          {!started && (
            <Button
              type="button"
              className="mt-4 h-11 w-full sm:w-auto"
              disabled={!hasDuration}
              onClick={() => setDeadline(performance.now() + duration * 60_000)}
            >
              <Clock className="size-4" aria-hidden="true" />
              Iniciar tiempo
            </Button>
          )}
          <div role="status">
            {notice && (
              <p className="mt-3 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm font-medium text-warn-fg">
                {notice}
              </p>
            )}
          </div>
        </div>
      </div>
    </section>
  )
}

export function ExamTimerPage() {
  const { status, data, error, reload } = useAsyncResource(examsService.listExams, [])
  const [selectedId, setSelectedId] = useState('')
  const exams = (data ?? []).filter((exam) => AVAILABLE_STATES.has(exam.estado))
  const selectedExam = exams.find((exam) => String(exam.id_examen) === selectedId)

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: TITLE }]}>
      <PageHeader title={TITLE} subtitle="Seleccione un examen para controlar su tiempo de resolución." />

      {status === 'loading' && <LoadingState rows={3} label="Cargando sus exámenes" />}
      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}
      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={Clock}
          title="No tiene exámenes disponibles"
          description="Aquí aparecerán sus exámenes programados y en curso."
        />
      )}

      {status === 'success' && exams.length > 0 && (
        <>
          <div className="space-y-2 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
            <Label htmlFor="timer-exam">Examen</Label>
            <Select value={selectedId} onValueChange={setSelectedId}>
              <SelectTrigger id="timer-exam" className="w-full sm:max-w-md">
                <SelectValue placeholder="Seleccione un examen" />
              </SelectTrigger>
              <SelectContent>
                {exams.map((exam) => (
                  <SelectItem key={exam.id_examen} value={String(exam.id_examen)}>
                    {[exam.nombre_examen, exam.materia?.nombre, formatDate(exam.fecha), exam.hora_inicio].filter(Boolean).join(' · ')}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-sm text-muted-foreground">
              El cronómetro funciona en esta vista. Si cambia de examen, recarga la página o sale, perderá el tiempo en curso.
            </p>
          </div>
          {selectedExam && <ExamCountdown key={selectedExam.id_examen} exam={selectedExam} />}
        </>
      )}
    </AppShell>
  )
}
