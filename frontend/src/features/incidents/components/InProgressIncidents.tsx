import { useEffect, useRef, useState } from 'react'
import type { ExamInProgress, InProgressStudent } from '@/features/exams'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api-client'
import type { ExamIncidentContext } from '../types/incident.types'
import { canReport, loadIncidentReportContext } from './incidentReportContext'
import { ReportIncidentDialog } from './ReportIncidentDialog'

interface Props { exam: ExamInProgress; student: InProgressStudent }

/** Reutiliza el formulario de HU-042 con un origen independiente del intento de ingreso. */
export function InProgressIncidentAction({ exam, student }: Props) {
  const [context, setContext] = useState<ExamIncidentContext | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [minimumCount, setMinimumCount] = useState(0)
  const pending = useRef<AbortController | null>(null)
  const count = Math.max(student.total_incidencias ?? 0, minimumCount)
  const available = canReport(exam, student)

  useEffect(() => () => pending.current?.abort(), [exam.id_examen, student.id_estudiante])

  async function open() {
    if (!available || pending.current) return
    const controller = new AbortController()
    pending.current = controller
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const loaded = await loadIncidentReportContext(exam.id_examen, student.id_estudiante, controller.signal)
      if (controller.signal.aborted) return
      if (!loaded) {
        setError('El examen o el estudiante cambió. Espere la actualización de la vista.')
        return
      }
      setMinimumCount((value) => Math.max(value, loaded.student.total_incidencias ?? 0))
      setContext(loaded.context)
    } catch (cause) {
      if (!controller.signal.aborted) {
        setError(cause instanceof ApiError && [403, 404, 409].includes(cause.status)
          ? cause.message : 'No se pudo cargar el contexto. Intente nuevamente.')
      }
    } finally {
      if (!controller.signal.aborted) setLoading(false)
      if (pending.current === controller) pending.current = null
    }
  }

  return <div className="space-y-2">
    {count > 0 && <p className="text-xs font-medium text-warn-fg">
      {count === 1 ? 'Incidencia reportada' : `${count} incidencias`}
    </p>}
    {available && <Button type="button" variant="outline" disabled={loading} onClick={() => void open()}>
      {loading ? 'Cargando reporte…' : 'Reportar'}
    </Button>}
    {error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
    {message && <p role="status" className="text-sm text-muted-foreground">{message}</p>}
    {context && <ReportIncidentDialog
      context={context}
      readOnly={!available || student.id_ambiente !== context.roomId}
      onClose={() => setContext(null)}
      onReported={() => {
        setContext(null)
        setMinimumCount(count + 1)
        setMessage('Enviado a revisión del docente')
        // La tabla y el contador compartido se actualizan con el polling de HU-038-A.
      }}
    />}
  </div>
}

export function InProgressIncidentsCount({ exam }: { exam: ExamInProgress }) {
  return <span role="status" className="rounded-full bg-warn-soft px-3 py-1 text-xs font-medium text-warn-fg">
    Incidencias pendientes: {exam.totales.incidencias_pendientes}
  </span>
}
