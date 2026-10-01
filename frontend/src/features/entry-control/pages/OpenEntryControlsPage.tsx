import { useEffect, useState } from 'react'
import { ArrowRight, Clock3, DoorOpen, MapPin } from 'lucide-react'
import { Link } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { entryControlService } from '../services/entryControlService'
import type { OpenEntryExam } from '../types/entry-control.types'

export function OpenEntryControlsPage() {
  const [exams, setExams] = useState<OpenEntryExam[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [retry, setRetry] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    let inFlight = false

    async function load(initial: boolean) {
      if (inFlight) return
      inFlight = true
      try {
        const next = await entryControlService.openExams(controller.signal)
        if (!controller.signal.aborted) { setExams(next); setError(null) }
      } catch (cause) {
        if (initial && !controller.signal.aborted) {
          setError(cause instanceof Error ? cause.message : 'No se pudieron cargar los exámenes.')
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

  return <AppShell mobileTitle="Control de ingreso" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Control de ingreso' }]}>
    <div><h1 className="sciem-h1">Control de ingreso</h1><p className="mt-1 text-sm text-muted-foreground">Exámenes programados o con ingreso abierto en los que participa.</p></div>
    {loading && <p role="status" className="rounded-xl border bg-card p-5 text-muted-foreground">Cargando controles de ingreso…</p>}
    {!loading && error && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-soft p-5"><p>{error}</p><Button variant="outline" className="mt-3" onClick={() => { setError(null); setRetry((value) => value + 1) }}>Reintentar</Button></div>}
    {!loading && !error && exams.length === 0 && <div className="rounded-xl border bg-card p-8 text-center"><DoorOpen className="mx-auto size-9 text-muted-foreground" /><h2 className="mt-3 font-semibold">No hay exámenes disponibles</h2><p className="mt-1 text-sm text-muted-foreground">Sus exámenes programados y los controles que tenga asignados aparecerán aquí.</p></div>}
    {!loading && !error && <ul className="grid gap-4 md:grid-cols-2">
      {exams.map((exam) => <li key={exam.id_examen} className="rounded-xl border bg-card p-5 shadow-sm">
        <p className="sciem-overline text-brand-deep">{exam.estado === 'EN_INGRESO' ? 'Ingreso abierto' : 'Programado'}</p><h2 className="mt-1 text-lg font-semibold">{exam.nombre_examen}</h2>
        {exam.materia && <p className="text-sm text-muted-foreground">{exam.materia}</p>}
        <div className="mt-3 space-y-1 text-sm text-muted-foreground"><p className="flex items-center gap-2"><Clock3 className="size-4" />{exam.fecha ?? '—'} · {exam.hora_inicio ?? '—'}</p><p className="flex items-center gap-2"><MapPin className="size-4" />{exam.ambientes.join(', ') || 'Ambiente pendiente'}</p></div>
        <Button className="mt-4 h-11 w-full" asChild><Link to={`/examenes/${exam.id_examen}/control-ingreso`}>{exam.estado === 'EN_INGRESO' ? 'Controlar ingreso' : 'Ver estado del control'} <ArrowRight className="size-4" /></Link></Button>
      </li>)}
    </ul>}
  </AppShell>
}
