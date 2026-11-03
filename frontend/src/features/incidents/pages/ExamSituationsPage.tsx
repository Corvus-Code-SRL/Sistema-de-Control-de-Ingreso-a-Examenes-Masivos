import { useCallback, useEffect, useRef, useState } from 'react'
import { Clock } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { PageHeader } from '@/components/common/PageHeader'
import { FormSelect } from '@/components/common/FormSelect'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { ApiError } from '@/lib/api-client'
import { ExamSituationDialog } from '../components/ExamSituationDialog'
import { EXAM_SITUATION_MESSAGES, SITUATION_EXAM_STATE_LABELS } from '../components/examSituationMessages'
import { ExamSituationsPanel } from '../components/ExamSituationsPanel'
import { useExamSituationWorkspace } from '../hooks/useExamSituationWorkspace'
import { examSituationService } from '../services/examSituationService'
import {
  formatSituationTime, type ExamSituationContext, type SituationCreated,
} from '../types/exam-situation.types'

/**
 * Acceso independiente para los controladores autorizados; no abre ni inicia exámenes.
 * Lista los exámenes con el ingreso abierto o en curso; sin ninguno, explica el estado vacío.
 */
export function ExamSituationsPage() {
  const [page, setPage] = useState(1)
  const [examId, setExamId] = useState('')
  return (
    <AppShell mobileTitle="Situaciones excepcionales" breadcrumbs={[{ label: 'Situaciones excepcionales' }]}>
      <PageHeader title="Registrar situación excepcional"
        subtitle="Cambios de ambiente y observaciones de identidad de estudiantes presentes." />
      <ExamSelectionPage key={page} page={page} examId={examId}
        onSelect={setExamId} onPage={(value) => { setExamId(''); setPage(value) }} />
    </AppShell>
  )
}

interface SelectionProps {
  page: number
  examId: string
  onSelect: (value: string) => void
  onPage: (value: number) => void
}

function ExamSelectionPage({ page, examId, onSelect, onPage }: SelectionProps) {
  const resource = useAsyncResource(useCallback(
    (signal) => examSituationService.exams(page, signal), [page]
  ), [page])
  if (resource.status === 'error' && resource.error) return <ErrorState error={resource.error} onRetry={resource.reload} />
  if (!resource.data) return <LoadingState rows={2} label="Cargando exámenes" />
  const catalog = resource.data
  if (!catalog.data.length && page <= 1) {
    return <EmptyState icon={Clock} title={EXAM_SITUATION_MESSAGES.emptyTitle}
      description={EXAM_SITUATION_MESSAGES.emptyDescription} />
  }
  return <>
    <Card className="space-y-3 p-4">
      <Label htmlFor="situation-exam">Examen</Label>
      {catalog.data.length ? <FormSelect id="situation-exam" value={examId} placeholder="Seleccione un examen"
        onValueChange={onSelect} options={catalog.data.map((exam) => ({
          value: String(exam.id_examen),
          label: `${exam.nombre_examen} · ${exam.fecha} · ${SITUATION_EXAM_STATE_LABELS[exam.estado]} · #${exam.id_examen}`,
        }))} /> : <p role="status">No hay más exámenes en esta página.</p>}
      <Pagination page={catalog.meta.current_page} lastPage={catalog.meta.last_page} onChange={onPage} label="exámenes" />
    </Card>
    {examId && <SituationWorkspaceFilters key={examId} examId={Number(examId)} />}
  </>
}

function SituationWorkspaceFilters({ examId }: { examId: number }) {
  const [roomId, setRoomId] = useState<number | undefined>()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  return <SituationWorkspace key={`${examId}-${roomId ?? 'all'}-${search}-${page}`}
    examId={examId} roomId={roomId} search={search} page={page}
    onRoom={(value) => { setRoomId(value); setPage(1) }}
    onSearch={(value) => { setSearch(value); setPage(1) }} onPage={setPage} />
}

interface WorkspaceProps {
  examId: number
  roomId?: number
  search: string
  page: number
  onRoom: (value: number | undefined) => void
  onSearch: (value: string) => void
  onPage: (value: number) => void
}

function SituationWorkspace({ examId, roomId, search, page, onRoom, onSearch, onPage }: WorkspaceProps) {
  const resource = useExamSituationWorkspace(examId, roomId, search, page)
  const [draft, setDraft] = useState(search)
  const [context, setContext] = useState<ExamSituationContext | null>(null)
  const [message, setMessage] = useState('')
  const [opening, setOpening] = useState(false)
  const openingRequest = useRef<AbortController | null>(null)
  useEffect(() => () => { openingRequest.current?.abort() }, [])

  async function openSituation(studentId: number) {
    if (openingRequest.current) return
    const controller = new AbortController()
    openingRequest.current = controller
    setOpening(true)
    setMessage('')
    try {
      // Se vuelve a consultar para no abrir con un aula o una hora de una carga anterior.
      const { data } = await examSituationService.context(examId, {
        id_estudiante: studentId, id_ambiente: roomId,
      }, controller.signal)
      if (controller.signal.aborted) return
      const student = data.estudiantes.find((item) => item.id_estudiante === studentId)
      if (!student) {
        setMessage('El examen o la asistencia cambió. Se actualizó la vista; seleccione nuevamente al estudiante.')
        resource.reload()
        return
      }
      setContext({
        examId, examName: data.examen.nombre_examen, student,
        room: student.ambiente, rooms: data.ambientes, serverTime: data.hora_servidor,
      })
    } catch (cause) {
      if (!controller.signal.aborted) {
        setMessage(cause instanceof ApiError && !cause.isServerError
          ? cause.message : 'No se pudo consultar la situación actual del estudiante.')
        resource.reload()
      }
    } finally {
      if (!controller.signal.aborted) setOpening(false)
      openingRequest.current = null
    }
  }

  function handleRegistered(result: SituationCreated) {
    setContext(null)
    setMessage(result.mensaje)
    resource.reload()
  }

  const data = resource.data
  const canRegister = !!data && !resource.error
  const effectiveRoom = data?.ambiente_control ?? roomId
  return <>
    {message && <p role="status" className="text-sm">{message}</p>}
    {resource.error && <ErrorState error={resource.error} onRetry={resource.reload} />}
    {!data && !resource.error && <LoadingState rows={3} label="Cargando estudiantes presentes" />}
    {data && <>
      <Card className="space-y-4 p-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="situation-room-filter">Ambiente</Label>
            <FormSelect id="situation-room-filter" value={String(effectiveRoom ?? 'all')}
              placeholder="Todos los ambientes" disabled={data.ambiente_control !== null}
              onValueChange={(value) => onRoom(value === 'all' ? undefined : Number(value))}
              options={[...(data.ambiente_control === null ? [{ value: 'all', label: 'Todos los ambientes' }] : []),
                ...data.ambientes.map((room) => ({ value: String(room.id_ambiente), label: room.nro_aula }))]} />
          </div>
          <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); onSearch(draft.trim()) }}>
            <Label htmlFor="situation-student-search">Nombre, código SIS o CI</Label>
            <div className="flex gap-2">
              <Input id="situation-student-search" value={draft} maxLength={50}
                onChange={(event) => setDraft(event.target.value)} />
              <Button type="submit" variant="outline">Buscar</Button>
            </div>
          </form>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-base font-semibold">Estudiantes presentes ({data.paginacion.total})</h2>
          <Button type="button" variant="outline" size="sm" onClick={resource.reload}>Actualizar</Button>
        </div>
        <p className="text-xs text-muted-foreground">
          {resource.error ? 'Datos sin actualizar desde' : 'Última actualización:'} {formatSituationTime(data.hora_servidor)}
        </p>
        {!data.estudiantes.length && <p>No hay estudiantes presentes para este filtro.</p>}
        <ul className="space-y-3" aria-label="Estudiantes presentes">
          {data.estudiantes.map((student) => {
            const change = student.situaciones.ultimo_cambio_ambiente
            return <li key={student.id_estudiante} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
              <div className="min-w-0 space-y-1 break-words">
                <p className="font-medium">{student.nombre_completo}</p>
                <p className="text-sm text-muted-foreground">{student.cod_sis} · Ambiente {student.ambiente.nro_aula}</p>
                <p className="text-sm">{student.estado_ingreso === 'CON_RETRASO' ? 'Presente con retraso' : 'Presente'}</p>
                {change && <p className="text-sm text-brand">Cambio desde {change.ambiente_origen.nro_aula} · {formatSituationTime(change.registrado_en)}</p>}
                {student.situaciones.identidad_observada && <p className="text-sm text-warn-fg">Identidad observada</p>}
              </div>
              <Button type="button" variant="outline" disabled={!canRegister || opening}
                onClick={() => { void openSituation(student.id_estudiante) }}>Registrar situación</Button>
            </li>
          })}
        </ul>
        <Pagination page={data.paginacion.current_page} lastPage={data.paginacion.last_page} onChange={onPage} label="estudiantes" />
      </Card>
      <section className="space-y-3" aria-label="Historial de situaciones">
        <h2 className="text-base font-semibold">Situaciones</h2>
        <ExamSituationsPanel key={effectiveRoom ?? 'all'} examId={examId} roomId={effectiveRoom}
          refreshKey={resource.revision} />
      </section>
    </>}
    {context && <ExamSituationDialog context={context} readOnly={!canRegister}
      onClose={() => { setContext(null); resource.reload() }} onRegistered={handleRegistered} />}
  </>
}

function Pagination({ page, lastPage, onChange, label }: {
  page: number; lastPage: number; onChange: (value: number) => void; label: string
}) {
  if (lastPage <= 1 && page <= 1) return null
  return <nav aria-label={`Paginación de ${label}`} className="flex flex-wrap items-center gap-3 text-sm">
    <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>Anterior</Button>
    <span>Página {page} de {lastPage}</span>
    <Button type="button" variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Siguiente</Button>
  </nav>
}