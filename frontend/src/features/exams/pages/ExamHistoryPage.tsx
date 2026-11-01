import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ChevronRight, History, Search } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { examHistoryService } from '../services/examHistoryService'
import type { HistoryList } from '../types/examHistory.types'

function formatDate(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : value
}

export function ExamHistoryPage() {
  const [params, setParams] = useSearchParams()
  const materia = params.get('materia') ?? ''
  const buscar = params.get('buscar') ?? ''
  const page = params.get('page') ?? '1'
  const [search, setSearch] = useState(buscar)

  useEffect(() => setSearch(buscar), [buscar])
  useEffect(() => {
    if (search === buscar) return
    const timer = window.setTimeout(() => {
      const next = new URLSearchParams(params)
      if (search.trim()) next.set('buscar', search.trim())
      else next.delete('buscar')
      next.delete('page')
      setParams(next)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [search, buscar, params, setParams])

  const { data, status, error, reload } = useAsyncResource<HistoryList>(
    useCallback((signal) => examHistoryService.list({ materia, buscar, page }, signal), [materia, buscar, page]),
    [materia, buscar, page]
  )

  function updateParam(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const query = params.toString()

  return <AppShell mobileTitle="Historial" breadcrumbs={[{ label: 'Exámenes' }, { label: 'Historial' }]}>
    <PageHeader title="Historial" subtitle="Sus exámenes finalizados. Solo lectura." />
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
      <Select value={materia || 'todas'} onValueChange={(value) => updateParam('materia', value === 'todas' ? '' : value)}>
        <SelectTrigger aria-label="Filtrar por materia" className="w-full bg-card sm:w-[260px]"><SelectValue placeholder="Todas las materias" /></SelectTrigger>
        <SelectContent position="popper" align="start" sideOffset={4}><SelectItem value="todas">Todas las materias</SelectItem>
          {(data?.materias ?? []).map((subject) => <SelectItem key={subject.id_materia} value={String(subject.id_materia)}>{subject.nombre}</SelectItem>)}
        </SelectContent>
      </Select>
      <label className="relative block w-full sm:max-w-[300px]">
        <span className="sr-only">Buscar examen</span>
        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <Input className="bg-card pl-10" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar examen" />
      </label>
    </div>
    <Card className="gap-0 py-0">
      {status === 'loading' && <LoadingState rows={4} label="Cargando historial" />}
      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}
      {status === 'success' && data?.examenes.length === 0 && <EmptyState icon={History} title={materia || buscar ? 'No hay exámenes que coincidan' : 'Aún no tiene exámenes finalizados'} description={materia || buscar ? 'Pruebe con otra materia o búsqueda.' : 'Aquí aparecerán sus exámenes cuando finalicen.'} />}
      {status === 'success' && data && data.examenes.length > 0 && <>
        <div className="hidden md:block"><Table><TableHeader className="bg-sunken"><TableRow>
          <TableHead className="pl-4">Fecha</TableHead><TableHead>Examen</TableHead><TableHead>Materia</TableHead><TableHead className="text-center">Asistencia</TableHead><TableHead className="text-center">Incidencias</TableHead><TableHead><span className="sr-only">Acción</span></TableHead>
        </TableRow></TableHeader><TableBody>{data.examenes.map((exam) => <TableRow key={exam.id_examen}>
          <TableCell className="whitespace-nowrap pl-4 tabular-nums">{formatDate(exam.fecha)}</TableCell><TableCell className="font-semibold">{exam.nombre_examen}</TableCell><TableCell>{exam.materia ?? '—'}</TableCell>
          <TableCell className="text-center tabular-nums">{exam.presentes} / {exam.esperados}</TableCell><TableCell className="text-center tabular-nums">{exam.incidencias}</TableCell>
          <TableCell><Button variant="outline" size="sm" asChild><Link to={`/historial/${exam.id_examen}${query ? `?${query}` : ''}`}>Ver resumen <ChevronRight className="size-4" aria-hidden="true" /></Link></Button></TableCell>
        </TableRow>)}</TableBody></Table></div>
        <div className="divide-y md:hidden">{data.examenes.map((exam) => <div key={exam.id_examen} className="space-y-3 p-4">
          <div className="flex items-start justify-between gap-3">
            <div><p className="font-semibold">{exam.nombre_examen}</p><p className="text-sm text-muted-foreground">{exam.materia ?? '—'} · {formatDate(exam.fecha)}</p></div>
            <Link to={`/historial/${exam.id_examen}${query ? `?${query}` : ''}`} aria-label={`Ver resumen de ${exam.nombre_examen}`} className="rounded-md border border-border p-2 text-brand"><ChevronRight className="size-4" /></Link>
          </div>
          <p className="text-sm text-muted-foreground">Asistencia <strong className="text-foreground">{exam.presentes} / {exam.esperados}</strong><span className="mx-2">·</span>Incidencias <strong className="text-foreground">{exam.incidencias}</strong></p>
        </div>)}</div>
        {data.paginas > 1 && <div className="flex items-center justify-between gap-3 border-t p-4 text-sm">
          <span>Página {data.pagina} de {data.paginas} · {data.total} exámenes</span>
          <div className="flex gap-2"><Button variant="outline" size="sm" disabled={data.pagina <= 1} onClick={() => updateParam('page', String(data.pagina - 1))}>Anterior</Button>
            <Button variant="outline" size="sm" disabled={data.pagina >= data.paginas} onClick={() => updateParam('page', String(data.pagina + 1))}>Siguiente</Button></div>
        </div>}
      </>}
    </Card>
  </AppShell>
}
