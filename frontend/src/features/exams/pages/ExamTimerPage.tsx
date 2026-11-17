import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Clock } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ApiError } from '@/lib/api-client'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { formatExamTimer, TIMER_WARNING_SECONDS, useExamTimerRemaining } from '../hooks/useExamTimerRemaining'
import { useExamTimers } from '../hooks/useExamTimers'
import { examTimerService, type ExamTimer } from '../services/examTimerService'
import { EXAM_STATUS } from '../types/exams.types'

const TITLE = 'Controlar tiempo del examen'
const MINUTE_MS = 60_000
const MAX_MINUTES = 1439

function formatDate(value: string) {
  return value?.split('-').reverse().join('/') || '—'
}

function ExamCountdown({ exam }: { exam: ExamTimer }) {
  const { clock, ready, accept } = useExamTimers()
  const timer = exam.cronometro_iniciado_en === null ? undefined : exam
  const seconds = useExamTimerRemaining(timer, clock)
  const [minutes, setMinutes] = useState(String(exam.duracion ?? ''))
  const [adjusting, setAdjusting] = useState(false)
  const [checking, setChecking] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const requestRef = useRef<AbortController | null>(null)
  const duration = exam.duracion
  const limit = Date.parse(exam.fin_permitido_en ?? '')
  const limitSeconds = useExamTimerRemaining({ ...exam, cronometro_vence_en: exam.fin_permitido_en }, clock)
  const expired = limitSeconds === 0
  const canAdjust = timer && !expired && Number.isFinite(limit)
  const count = Number(minutes)
  const validMinutes = Number.isInteger(count) && count >= 1 && count <= MAX_MINUTES

  useEffect(() => () => requestRef.current?.abort(), [])

  function openAdjustment() {
    if (!timer) return
    const elapsedMinutes = Math.ceil((Date.parse(timer.cronometro_vence_en ?? '') - Date.parse(timer.cronometro_iniciado_en ?? '')) / MINUTE_MS)
    setMinutes(String(elapsedMinutes))
    setError(null)
    setAdjusting(true)
  }

  async function save() {
    if (requestRef.current || !ready || !validMinutes || expired || (timer && !adjusting)) return

    const controller = new AbortController()
    requestRef.current = controller
    setChecking(true)
    setError(null)

    try {
      if (!adjusting) {
        const detail = await examTimerService.get(exam.id_examen, controller.signal)
        if (controller.signal.aborted) return
        const latest = detail.examen
        if (latest.estado !== EXAM_STATUS.EN_CURSO) {
          setError('El examen ya no está disponible para iniciar el cronómetro.')
          return
        }
        if (latest.cronometro_iniciado_en !== null) {
          accept(detail)
          return
        }

        const finish = latest.fin_permitido_en
        const availableMs = Date.parse(finish ?? '') - Date.parse(detail.hora_servidor_en)
        if (!finish || !Number.isFinite(availableMs)) {
          setError('No se pudo determinar la hora de fin programada del examen.')
          return
        }
        if (availableMs <= 0) {
          setError('La hora de fin programada ya pasó. No se puede iniciar el cronómetro.')
          return
        }
      }

      const result = await examTimerService.save(exam.id_examen, count, adjusting, controller.signal)
      if (controller.signal.aborted) return
      accept(result)
      setAdjusting(false)
    } catch (cause) {
      if (controller.signal.aborted) return
      setError(cause instanceof ApiError && cause.status >= 400 && cause.status < 500
        ? cause.errors.minutos?.[0] ?? cause.message
        : 'No se pudo guardar el cronómetro. Intente nuevamente.')
    } finally {
      requestRef.current = null
      if (!controller.signal.aborted) setChecking(false)
    }
  }

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
            <dd className="sciem-tnum font-medium">{exam.hora_inicio && exam.hora_fin ? `${exam.hora_inicio}–${exam.hora_fin}` : '—'}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Duración prevista</dt>
            <dd className="sciem-tnum font-medium">{duration && duration > 0 ? `${duration} min` : 'Sin duración registrada'}</dd>
          </div>
        </dl>

        <div className="rounded-lg bg-sunken p-4">
          <p id="exam-time-remaining" className="text-sm text-muted-foreground">Tiempo restante</p>
          <p role="timer" aria-labelledby="exam-time-remaining" className="sciem-tnum mt-1 text-3xl font-semibold">
            {seconds === null ? '—' : formatExamTimer(seconds)}
          </p>
          <div role="status">
            {seconds !== null && seconds <= TIMER_WARNING_SECONDS && (
              <p className="mt-3 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm font-medium text-warn-fg">
                {seconds === 0 ? 'Tiempo agotado' : 'Quedan 10 minutos o menos.'}
              </p>
            )}
            {checking && <p className="mt-3 text-sm text-muted-foreground">Verificando el cronómetro…</p>}
          </div>

          {(!timer || adjusting) && (
            <div className="mt-4 space-y-2">
              <Label htmlFor="exam-timer-minutes">{adjusting ? 'Duración total en minutos' : 'Minutos concedidos'}</Label>
              <Input id="exam-timer-minutes" type="number" min={1} max={MAX_MINUTES} step={1} value={minutes} disabled={checking || expired}
                onChange={(event) => setMinutes(event.target.value)} />
              {adjusting && <p className="text-sm text-muted-foreground">Se cuentan desde el inicio del cronómetro, hasta el fin programado como máximo.</p>}
              <div className="flex flex-wrap gap-2">
                <Button type="button" disabled={!ready || !validMinutes || checking || expired} onClick={() => void save()}>
                  <Clock className="size-4" aria-hidden="true" />
                  {adjusting ? 'Guardar ajuste' : 'Iniciar cronómetro'}
                </Button>
                {adjusting && (
                  <Button type="button" variant="outline" disabled={checking} onClick={() => {
                    setAdjusting(false)
                    setError(null)
                  }}>Volver</Button>
                )}
              </div>
            </div>
          )}
          {expired && <p role="status" className="mt-3 text-sm text-warn-fg">La hora final permitida ya pasó. No se puede iniciar ni ajustar el cronómetro.</p>}
          {canAdjust && !adjusting && (
            <Button type="button" variant="outline" className="mt-4" onClick={openAdjustment}>Ajustar tiempo</Button>
          )}
          {error && <p role="alert" className="mt-3 text-sm text-danger-fg">{error}</p>}
        </div>
      </div>
    </section>
  )
}

export function ExamTimerPage() {
  const { exams, timers, closed, ready, error: pollError } = useExamTimers()
  const [search, setSearch] = useSearchParams()
  const selectedId = search.get('exam') ?? (timers[0] ? String(timers[0].id_examen) : '')
  const selected = exams.find(({ id_examen }) => String(id_examen) === selectedId)
  const closedExam = closed.find(({ id_examen }) => String(id_examen) === selectedId)
    ?? (selectedId === '' ? closed[closed.length - 1] : undefined)

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: TITLE }]}>
      <PageHeader title={TITLE} subtitle="Seleccione un examen para controlar su tiempo de resolución." />
      {closedExam && <p role="alert" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">
        El examen «{closedExam.nombre_examen}» ya no está disponible para controlar el tiempo.
      </p>}
      {pollError && <p role="alert" className="text-sm text-warn-fg">{pollError}</p>}
      {!ready && !pollError && <LoadingState rows={1} label="Recuperando cronómetros" />}
      {ready && exams.length === 0 && (
        <EmptyState icon={Clock} title="No tiene exámenes disponibles" description="Aquí aparecerán sus exámenes en curso." />
      )}
      {ready && exams.length > 0 && (
        <>
          <div className="space-y-2 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
            <Label htmlFor="timer-exam">Examen</Label>
            <Select value={selected ? selectedId : ''} onValueChange={(id) => setSearch({ exam: id }, { replace: true })}>
              <SelectTrigger id="timer-exam" className="w-full sm:max-w-md"><SelectValue placeholder="Seleccione un examen" /></SelectTrigger>
              <SelectContent>
                {exams.map((exam) => (
                  <SelectItem key={exam.id_examen} value={String(exam.id_examen)}>
                    {[exam.nombre_examen, exam.materia?.nombre, formatDate(exam.fecha), exam.hora_inicio].filter(Boolean).join(' · ')}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {selected && <ExamCountdown key={selected.id_examen} exam={selected} />}
        </>
      )}
    </AppShell>
  )
}
