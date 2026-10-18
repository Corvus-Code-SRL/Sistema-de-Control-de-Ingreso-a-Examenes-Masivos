import { useEffect, useState } from 'react'
import { attemptTime, rejectionLabel } from './entryAttemptPresentation'
import { Button } from '@/components/ui/button'
import type { ReportIncidentContext } from '@/features/incidents'
import { entryControlService } from '../services/entryControlService'
import type { RejectedAttempt } from '../types/entry-control.types'

interface Props {
  examId: number
  examName: string
  roomId: number
  refreshVersion: number
  onReport: (context: ReportIncidentContext) => void
  onCount?: (count: number) => void
}

export function RecentRejectedAttempts({ examId, examName, roomId, refreshVersion, onReport, onCount }: Props) {
  const [attempts, setAttempts] = useState<RejectedAttempt[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [retryVersion, setRetryVersion] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setError(null)
    setAttempts([])
    void entryControlService.rejectedAttempts(examId, roomId, controller.signal)
      .then((items) => {
        if (!controller.signal.aborted) { setAttempts(items); onCount?.(items.length) }
      })
      .catch(() => {
        if (!controller.signal.aborted) setError('No se pudieron cargar los últimos rechazos.')
      })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [examId, roomId, refreshVersion, retryVersion, onCount])

  return <section className="space-y-3 rounded-xl border bg-card p-4" aria-label="Últimos rechazos">
    <div className="flex items-center justify-between gap-3">
      <h2 className="font-semibold">Últimos rechazos</h2>
      <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => setRetryVersion((value) => value + 1)}>Actualizar rechazos</Button>
    </div>
    {loading && <p role="status" className="text-sm text-muted-foreground">Cargando rechazos…</p>}
    {error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
    {!loading && !error && attempts.length === 0 && <p className="text-sm text-muted-foreground">No hay intentos rechazados en este ambiente.</p>}
    {attempts.length > 0 && <ul className="divide-y">
      {attempts.map((attempt) => <li key={attempt.id_intento} className="flex flex-wrap items-center justify-between gap-3 py-3">
        <div className="flex min-w-0 gap-3 text-sm">
          <time dateTime={attempt.registrado_en} className="sciem-tnum text-muted-foreground">{attemptTime(attempt.registrado_en)}</time>
          <div>
          <p className="font-medium">{attempt.estudiante?.nombre_completo ?? '—'}</p>
          {attempt.estudiante && <p className="sciem-tnum text-muted-foreground">SIS {attempt.estudiante.cod_sis}</p>}
          <p className="text-muted-foreground">{attempt.ambiente.nro_aula} · {rejectionLabel(attempt.motivo)}</p>
          {!attempt.estudiante && attempt.cod_sis && <p className="sciem-tnum text-muted-foreground">{attempt.cod_sis}</p>}
          </div>
        </div>
        {attempt.estudiante ? <Button
          type="button" variant="outline" size="sm" disabled={attempt.reportado}
          onClick={() => {
            if (!attempt.estudiante || attempt.reportado) return
            onReport({ examId, examName, roomId: attempt.ambiente.id_ambiente, attemptId: attempt.id_intento, student: attempt.estudiante,
              roomName: attempt.ambiente.nro_aula, reason: rejectionLabel(attempt.motivo), timeLabel: attemptTime(attempt.registrado_en) })
          }}
        >{attempt.reportado ? 'Ya reportado' : 'Reportar incidencia'}</Button> :
          <span className="text-xs text-muted-foreground">Sin estudiante identificado</span>}
      </li>)}
    </ul>}
  </section>
}