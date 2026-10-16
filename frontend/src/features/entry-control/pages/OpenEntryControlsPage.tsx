import { useEffect, useRef, useState } from 'react'
import { AlertOctagon, AlertTriangle, ArrowRight, Clock3, DoorOpen, MapPin, RefreshCw, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api-client'
import { entryControlService } from '../services/entryControlService'
import type { OpenEntryExam } from '../types/entry-control.types'

const CAMPUS_TIME_ZONE = 'America/La_Paz'

function timeLabel(value: string): string {
  return new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: CAMPUS_TIME_ZONE,
  }).format(new Date(value))
}

function openingMessage(exam: OpenEntryExam, now: number): string {
  const time = timeLabel(exam.apertura_programada_en)
  if (exam.estado === 'EN_INGRESO') return `Apertura programada: ${time}`
  const minutes = Math.ceil((new Date(exam.apertura_programada_en).getTime() - now) / 60_000)
  if (minutes <= 0) return `La apertura automática estaba prevista a las ${time}.`
  const remaining = minutes >= 1440
    ? `${Math.floor(minutes / 1440)} ${minutes < 2880 ? 'día' : 'días'}`
    : minutes >= 120
      ? `${Math.floor(minutes / 60)} horas`
      : `${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`
  return `Se abrirá automáticamente a las ${time} · ${remaining.startsWith('1 ') ? 'falta' : 'faltan'} ${remaining}.`
}

export function OpenEntryControlsPage() {
  const [exams, setExams] = useState<OpenEntryExam[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshError, setRefreshError] = useState<string | null>(null)
  const [updatedAt, setUpdatedAt] = useState<number | null>(null)
  const [now, setNow] = useState(Date.now())
  const [retry, setRetry] = useState(0)
  const hasLoaded = useRef(false)

  useEffect(() => {
    const controller = new AbortController()
    let inFlight = false

    async function load(initial: boolean) {
      if (inFlight) return
      inFlight = true
      try {
        const next = await entryControlService.openExams(controller.signal)
        if (!controller.signal.aborted) {
          hasLoaded.current = true
          setExams(next)
          setUpdatedAt(Date.now())
          setError(null)
          setRefreshError(null)
        }
      } catch (cause) {
        if (!controller.signal.aborted) {
          const message = cause instanceof ApiError && cause.isServerError
            ? 'El servidor no pudo responder. Intente nuevamente.'
            : cause instanceof Error ? cause.message : 'No se pudieron actualizar los controles.'
          if (hasLoaded.current) setRefreshError(message)
          else setError(message)
        }
      } finally {
        inFlight = false
        if (initial && !controller.signal.aborted) setLoading(false)
      }
    }

    setLoading(true)
    void load(true)
    const timer = window.setInterval(() => {
      if (!document.hidden) void load(false)
    }, 15000)
    return () => { controller.abort(); window.clearInterval(timer) }
  }, [retry])

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 30000)
    return () => window.clearInterval(timer)
  }, [])

  return <AppShell mobileTitle="Control de ingreso" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Control de ingreso' }]}>
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div><h1 className="sciem-h1">Control de ingreso</h1><p className="mt-1 text-sm text-muted-foreground">Exámenes con control abierto o por abrir en los que participa.</p></div>
      <Button type="button" variant="outline" size="sm" onClick={() => setRetry((value) => value + 1)}><RefreshCw className="size-4" />Actualizar</Button>
    </div>
    {loading && !hasLoaded.current && !error && <p role="status" className="rounded-xl border bg-card p-5 text-muted-foreground">Cargando controles de ingreso…</p>}
    {error && !hasLoaded.current && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-soft p-5"><p className="font-medium text-danger-fg">No se pudieron cargar los controles de ingreso.</p><p className="mt-1 text-sm">{error}</p><Button variant="outline" className="mt-3" onClick={() => setRetry((value) => value + 1)}>Reintentar</Button></div>}
    {refreshError && hasLoaded.current && <div role="alert" className="flex flex-wrap items-center gap-3 rounded-xl border border-warn-border bg-warn-soft p-4 text-sm text-warn-fg"><AlertTriangle className="size-5 shrink-0" aria-hidden="true" /><span className="flex-1">Datos sin actualizar{updatedAt === null ? '' : ` desde ${timeLabel(new Date(updatedAt).toISOString())}`}. {refreshError}</span></div>}
    {hasLoaded.current && updatedAt !== null && !refreshError && <p role="status" className="text-xs text-muted-foreground">Lista actualizada a las {timeLabel(new Date(updatedAt).toISOString())}.</p>}
    {hasLoaded.current && exams.length === 0 && <div className="rounded-xl border bg-card p-8 text-center"><DoorOpen className="mx-auto size-9 text-muted-foreground" /><h2 className="mt-3 font-semibold">No hay exámenes disponibles</h2><p className="mt-1 text-sm text-muted-foreground">Sus exámenes programados y los controles que tenga asignados aparecerán aquí.</p></div>}
    {hasLoaded.current && exams.length > 0 && <ul className="grid gap-4 md:grid-cols-2">
      {exams.map((exam) => {
        const failed = exam.estado === 'PROGRAMADO' && exam.motivo_apertura !== null
        return <li key={exam.id_examen} className="flex min-w-0 flex-col rounded-xl border border-border bg-card p-5 shadow-sm">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div className="min-w-0"><p className="text-sm text-muted-foreground">{[exam.materia, exam.carrera].filter(Boolean).join(' · ')}</p><h2 className="mt-1 text-lg font-semibold">{exam.nombre_examen}</h2></div>
            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${failed ? 'bg-danger-soft text-danger-fg' : exam.estado === 'EN_INGRESO' ? 'bg-ok-soft text-ok-fg' : 'bg-warn-soft text-warn-fg'}`}>{failed ? 'No se pudo abrir' : exam.estado === 'EN_INGRESO' ? 'Ingreso abierto' : 'Programado'}</span>
          </div>
          <div className="mt-3 space-y-1.5 text-sm text-muted-foreground">
            <p className="flex items-center gap-2"><Clock3 className="size-4 shrink-0" aria-hidden="true" />{exam.fecha ?? '—'} · examen {exam.hora_inicio ?? '—'}</p>
            <p className="flex items-center gap-2"><MapPin className="size-4 shrink-0" aria-hidden="true" />{exam.rol_controlador === 'AUXILIAR' ? `Usted controla en ${exam.ambiente_asignado ?? 'su ambiente asignado'}` : exam.ambientes.join(', ') || 'Ambiente pendiente'}</p>
            {exam.estado === 'EN_INGRESO' && <p className="flex items-center gap-2"><Users className="size-4 shrink-0" aria-hidden="true" />{exam.ingresados} de {exam.total} ingresaron · {exam.pendientes} pendientes</p>}
            {!failed && <p>{openingMessage(exam, now)}</p>}
          </div>
          {failed && <div role="alert" className="mt-3 flex gap-3 rounded-lg border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger-fg">
            <AlertOctagon className="mt-0.5 size-[18px] shrink-0" aria-hidden="true" />
            <div><p className="font-semibold">{exam.motivo_apertura?.mensaje}</p>
              {exam.motivo_apertura?.codigo === 'CAPACIDAD_INSUFICIENTE' && <p className="mt-1 text-xs">Agregue un ambiente al examen para que el control se abra.</p>}
            </div>
          </div>}
          <Button className="mt-4 h-11 w-full sm:w-auto sm:self-start" asChild><Link to={`/examenes/${exam.id_examen}/control-ingreso`}>{exam.estado === 'EN_INGRESO' ? 'Controlar ingreso' : failed ? 'Ver detalle del examen' : 'Ver estado del control'} <ArrowRight className="size-4" /></Link></Button>
        </li>
      })}
    </ul>}
  </AppShell>
}
