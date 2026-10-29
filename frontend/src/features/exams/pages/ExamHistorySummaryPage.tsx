import { useCallback } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { ArrowLeft, Check, Clock3, DoorOpen, Flag, History, Play, UserRoundCheck } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { examHistoryService } from '../services/examHistoryService'
import type { HistorySummary } from '../types/examHistory.types'

function formatDate(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : value
}

const eventStyles = {
  apertura: { Icon: DoorOpen, color: 'bg-accent-brand text-brand-deep' },
  inicio: { Icon: Play, color: 'bg-brand text-white' },
  ingreso: { Icon: UserRoundCheck, color: 'bg-ok text-white' },
  retraso: { Icon: Clock3, color: 'bg-accent-brand text-brand-deep' },
  incidencia: { Icon: Flag, color: 'bg-danger text-white' },
  fin: { Icon: Check, color: 'bg-neutral-fg text-white' },
} as const

export function ExamHistorySummaryPage() {
  const { id = '' } = useParams<{ id: string }>()
  const [params] = useSearchParams()
  const validId = /^[0-9]{1,9}$/.test(id) && Number(id) > 0
  const backTo = `/historial${params.toString() ? `?${params}` : ''}`
  const { data, status, error, reload } = useAsyncResource<HistorySummary>(
    useCallback((signal) => validId ? examHistoryService.show(Number(id), signal) : Promise.resolve(null as unknown as HistorySummary), [id, validId]),
    [id, validId]
  )

  return <AppShell mobileTitle="Resumen del examen" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Historial', to: backTo }, { label: data?.nombre_examen ?? 'Resumen' }]}>
    
    {!validId && <EmptyState icon={History} title="Examen no encontrado" description="Revise el enlace del historial." />}
    {validId && status === 'loading' && <LoadingState rows={4} label="Cargando resumen" />}
    {validId && status === 'error' && error && <ErrorState error={error} onRetry={reload} />}
    {validId && status === 'success' && data && <>
      <div>
        <div className="min-w-0">
          <Link to={backTo} className="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand"><ArrowLeft className="size-4" />Historial</Link>
          <h1 className="sciem-h1 break-words">{data.nombre_examen} · {data.materia ?? 'Examen'}</h1>
          <div className="mt-2 flex items-center gap-3 text-sm text-muted-foreground">
            <span className="inline-flex items-center gap-1 rounded-full bg-neutral-soft px-2.5 py-1 text-xs font-medium text-neutral-fg"><Check className="size-3" />Finalizado</span>
            <span>{formatDate(data.fecha)}</span>
          </div>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {([['A tiempo', data.totales.a_tiempo], ['Con retraso', data.totales.con_retraso], ['Ausentes', data.totales.ausentes], ['Incidencias', data.totales.incidencias]] as const).map(([label, value]) =>
          <Card key={label} className="gap-1 p-4"><strong className="text-3xl tabular-nums">{value}</strong><span className="text-sm text-muted-foreground">{label}</span></Card>)}
      </div>
      <div className="grid items-start gap-4 lg:grid-cols-2">
        <Card><CardHeader><CardTitle>Línea de tiempo</CardTitle></CardHeader><CardContent>
          {data.linea_tiempo.length === 0 ? <p className="text-sm text-muted-foreground">No hay actividades registradas.</p> :
            <ol>{data.linea_tiempo.map((event, index) => {
              const { Icon, color } = eventStyles[event.tipo]
              return <li key={`${event.fecha_hora}-${index}`} className="flex gap-3">
                <div className="flex w-7 shrink-0 flex-col items-center">
                  <span className={`z-10 flex size-6 items-center justify-center rounded-full ${color}`}><Icon className="size-3.5" aria-hidden="true" /></span>
                  {index < data.linea_tiempo.length - 1 && <span className="min-h-8 w-px flex-1 bg-border" aria-hidden="true" />}
                </div>
                <div className="min-w-0 pb-5"><p className="text-sm font-semibold"><time>{event.fecha_hora.slice(11, 16)}</time> · {event.descripcion}</p>
                  <p className="mt-0.5 break-words text-xs text-muted-foreground">{event.detalle}</p></div>
              </li>
            })}</ol>}
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Por ambiente</CardTitle></CardHeader><CardContent>
          {data.ambientes.length === 0 ? <p className="text-sm text-muted-foreground">No hay ambientes asignados.</p> :
            <Table><TableHeader><TableRow><TableHead>Ambiente</TableHead><TableHead>Presentes</TableHead><TableHead>Controló</TableHead></TableRow></TableHeader>
              <TableBody>{data.ambientes.map((room) => <TableRow key={room.id_ambiente}><TableCell>{room.nro_aula}</TableCell><TableCell>{room.presentes}</TableCell><TableCell>{room.auxiliares.join(', ') || '—'}</TableCell></TableRow>)}</TableBody></Table>}
        </CardContent></Card>
      </div>
    </>}
  </AppShell>
}
