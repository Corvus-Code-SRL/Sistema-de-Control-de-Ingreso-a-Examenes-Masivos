import { useCallback, useEffect, useRef, useState } from 'react'
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
import { ApiError } from '@/lib/api-client'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { useExamTimerStatus } from '../hooks/useExamTimerStatus'
import { examsService } from '../services/examsService'
import { EXAM_STATUS, type Exam, type ExamStatus } from '../types/exams.types'

const TITLE = 'Controlar tiempo del examen'
const WARNING_SECONDS = 10 * 60
const MINUTE_MS = 60_000
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

function isValidDuration(duration: number) {
  return Number.isInteger(duration) && duration > 0
}

interface TimerRun {
  exam: Exam
  endAt: number
  monotonicEnd: number
}

interface StartOffer {
  scheduledEnd: number
  duration: number
  message: string
}

function scheduledEndAt(exam: Exam) {
  const start = exam.inicio_programado
  if (!start || !/(?:Z|[+-]\d{2}:\d{2})$/.test(start) || !/^\d{2}:\d{2}/.test(exam.hora_fin)) {
    return NaN
  }

  // Same calculation as ExamTimingService::endsAt, using the offset supplied by the server.
  return Date.parse(start) + exam.duracion * MINUTE_MS
}

function remainingMs(run: TimerRun) {
  return Math.max(0, Math.min(run.endAt - Date.now(), run.monotonicEnd - performance.now()))
}

function availableTimeLabel(availableMs: number) {
  const seconds = Math.floor(availableMs / 1000)
  if (seconds === 0) return 'menos de 1 s'

  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return [minutes > 0 && `${minutes} min`, remainder > 0 && `${remainder} s`].filter(Boolean).join(' ')
}

interface ExamCountdownProps {
  exam: Exam
  run: TimerRun | undefined
  onStarted: (run: TimerRun) => void
  onClosed: (exam: Exam) => void
}

function ExamCountdown({ exam: initialExam, run, onStarted, onClosed }: ExamCountdownProps) {
  const exam = run?.exam ?? initialExam
  const duration = exam.duracion
  const hasDuration = isValidDuration(duration)
  const [remaining, setRemaining] = useState(() => run
    ? Math.ceil(remainingMs(run) / 1000)
    : hasDuration ? duration * 60 : 0)
  const [checking, setChecking] = useState(false)
  const [startError, setStartError] = useState<string | null>(null)
  const [offer, setOffer] = useState<StartOffer | null>(null)
  const startRequest = useRef<AbortController | null>(null)
  const started = run !== undefined
  const active = started && remaining > 0
  const { status: liveStatus, error: pollError } = useExamTimerStatus(exam.id_examen, active, onClosed)

  useEffect(() => () => startRequest.current?.abort(), [])

  async function verifyStart(confirmedOffer: StartOffer | null) {
    if (startRequest.current || !hasDuration || started) return

    const request = new AbortController()
    startRequest.current = request
    setChecking(true)
    setStartError(null)

    try {
      const latestExam = await examsService.getExam(exam.id_examen, request.signal)
      if (request.signal.aborted) return

      if (!AVAILABLE_STATES.has(latestExam.estado)) {
        setOffer(null)
        setStartError('El examen ya no está disponible para iniciar el cronómetro.')
        return
      }

      if (!isValidDuration(latestExam.duracion)) {
        setOffer(null)
        setStartError('El examen no tiene una duración válida para iniciar el cronómetro.')
        return
      }

      const scheduledEnd = scheduledEndAt(latestExam)
      if (!Number.isFinite(scheduledEnd)) {
        setOffer(null)
        setStartError('No se pudo determinar la hora de fin programada del examen.')
        return
      }

      const now = Date.now()
      const availableMs = scheduledEnd - now
      if (availableMs <= 0) {
        setOffer(null)
        setStartError('La hora de fin programada ya pasó. No se puede iniciar el cronómetro.')
        return
      }

      const durationMs = latestExam.duracion * MINUTE_MS
      const acceptedSchedule = confirmedOffer?.scheduledEnd === scheduledEnd && confirmedOffer.duration === latestExam.duracion
      if (availableMs < durationMs && !acceptedSchedule) {
        setOffer({
          scheduledEnd,
          duration: latestExam.duracion,
          message: `Quedan ${availableTimeLabel(availableMs)} de los ${latestExam.duracion} min previstos, hasta las ${latestExam.hora_fin.slice(0, 5)}.`,
        })
        return
      }

      const runMs = Math.min(durationMs, availableMs)
      setOffer(null)
      setRemaining(Math.ceil(runMs / 1000))
      onStarted({ exam: latestExam, endAt: now + runMs, monotonicEnd: performance.now() + runMs })
    } catch (error) {
      if (request.signal.aborted) return

      setStartError(error instanceof ApiError && (error.isUnauthorized || error.isForbidden)
        ? 'No tiene autorización para consultar este examen.'
        : 'No se pudo verificar el estado del examen. Intente nuevamente.')
    } finally {
      startRequest.current = null
      if (!request.signal.aborted) setChecking(false)
    }
  }

  useEffect(() => {
    if (!run) return

    let timer: number | undefined
    const update = () => {
      window.clearTimeout(timer)
      const ms = remainingMs(run)
      const seconds = Math.ceil(ms / 1000)
      setRemaining((previous) => Math.min(previous, seconds))
      if (ms > 0) timer = window.setTimeout(update, Math.ceil(ms - (seconds - 1) * 1000))
    }

    update()
    document.addEventListener('visibilitychange', update)

    return () => {
      window.clearTimeout(timer)
      document.removeEventListener('visibilitychange', update)
    }
  }, [run])

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
        <ExamStatusBadge status={liveStatus ?? exam.estado} />
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
          {!started && !offer && (
            <Button
              type="button"
              className="mt-4 h-11 w-full sm:w-auto"
              disabled={!hasDuration || checking}
              onClick={() => void verifyStart(null)}
            >
              <Clock className="size-4" aria-hidden="true" />
              Iniciar tiempo
            </Button>
          )}
          <div role="status">
            {checking && <p className="mt-3 text-sm text-muted-foreground">Verificando estado del examen…</p>}
            {offer && <p className="mt-3 text-sm font-medium text-warn-fg">{offer.message}</p>}
            {notice && (
              <p className="mt-3 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm font-medium text-warn-fg">
                {notice}
              </p>
            )}
          </div>
          {offer && (
            <div className="mt-3 flex flex-wrap gap-2">
              <Button type="button" disabled={checking} onClick={() => void verifyStart(offer)}>
                Iniciar con el tiempo disponible
              </Button>
              <Button type="button" variant="outline" disabled={checking} onClick={() => {
                setOffer(null)
                setStartError(null)
              }}>
                Volver
              </Button>
            </div>
          )}
          {startError && (
            <p role="alert" className="mt-3 text-sm font-medium text-danger-fg">{startError}</p>
          )}
          {active && pollError && <p role="alert" className="mt-3 text-sm text-warn-fg">{pollError}</p>}
        </div>
      </div>
    </section>
  )
}

export function ExamTimerPage() {
  const { status, data, error, reload } = useAsyncResource(examsService.listExams, [])
  const [selectedId, setSelectedId] = useState('')
  const [runs, setRuns] = useState<Record<number, TimerRun>>({})
  const [closedIds, setClosedIds] = useState<ReadonlySet<number>>(() => new Set())
  const [closedNotice, setClosedNotice] = useState<string | null>(null)
  const exams = (data ?? []).filter((exam) => AVAILABLE_STATES.has(exam.estado) && !closedIds.has(exam.id_examen))
  const selectedExam = exams.find((exam) => String(exam.id_examen) === selectedId)

  const onClosed = useCallback((exam: Exam) => {
    setClosedIds((previous) => new Set(previous).add(exam.id_examen))
    setSelectedId((previous) => previous === String(exam.id_examen) ? '' : previous)
    const condition = exam.estado === EXAM_STATUS.FINALIZADO ? 'finalizado' : 'cancelado'
    setClosedNotice(`El examen «${exam.nombre_examen}» fue ${condition} y ya no está disponible.`)
  }, [])

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: TITLE }]}>
      <PageHeader title={TITLE} subtitle="Seleccione un examen para controlar su tiempo de resolución." />

      {closedNotice && <p role="alert" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">{closedNotice}</p>}
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
            <Select value={selectedId} onValueChange={(id) => {
              setSelectedId(id)
              setClosedNotice(null)
            }}>
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
              El cronómetro funciona en esta vista. Si recarga la página o sale, perderá el tiempo en curso.
            </p>
          </div>
          {selectedExam && (
            <ExamCountdown
              key={selectedExam.id_examen}
              exam={selectedExam}
              run={runs[selectedExam.id_examen]}
              onStarted={(run) => setRuns((previous) => ({ ...previous, [run.exam.id_examen]: run }))}
              onClosed={onClosed}
            />
          )}
        </>
      )}
    </AppShell>
  )
}
