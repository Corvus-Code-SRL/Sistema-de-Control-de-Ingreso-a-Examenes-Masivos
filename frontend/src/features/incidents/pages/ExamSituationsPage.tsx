import { useEffect, useRef, useState } from 'react'
import { Clock } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ApiError } from '@/lib/api-client'
import { ExamPagination } from '../components/ExamPagination'
import { ExamSelection } from '../components/ExamSelection'
import { ExamSituationDialog } from '../components/ExamSituationDialog'
import { EXAM_SITUATION_MESSAGES, SITUATION_EXAM_STATE_LABELS } from '../components/examSituationMessages'
import { ExamSituationsPanel } from '../components/ExamSituationsPanel'
import { ExamStudentFilters } from '../components/ExamStudentFilters'
import { useExamSituationWorkspace } from '../hooks/useExamSituationWorkspace'
import { examSituationService } from '../services/examSituationService'
import {
  formatSituationTime, type ExamSituationContext, type SituationCreated,
} from '../types/exam-situation.types'

const fetchSituationExams = (page: number, signal?: AbortSignal) => examSituationService.exams(page, signal)

/**
 * Acceso independiente para los controladores autorizados; no abre ni inicia exámenes.
 * Lista los exámenes con el ingreso abierto o en curso; sin ninguno, explica el estado vacío.
 */
export function ExamSituationsPage() {
  return (
    <AppShell mobileTitle="Situaciones excepcionales" breadcrumbs={[{ label: 'Situaciones excepcionales' }]}>
      <PageHeader title="Registrar situación excepcional"
        subtitle="Cambios de ambiente y observaciones de identidad de estudiantes presentes." />
      <ExamSelection fetchExams={fetchSituationExams} selectId="situation-exam" icon={Clock}
        emptyTitle={EXAM_SITUATION_MESSAGES.emptyTitle}
        emptyDescription={EXAM_SITUATION_MESSAGES.emptyDescription}
        stateLabels={SITUATION_EXAM_STATE_LABELS}>
        {(examId) => <SituationWorkspaceFilters key={examId} examId={examId} />}
      </ExamSelection>
    </AppShell>
  )
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
        <ExamStudentFilters idPrefix="situation" rooms={data.ambientes} controlRoom={data.ambiente_control}
          effectiveRoom={effectiveRoom} draft={draft} onDraft={setDraft} onRoom={onRoom} onSearch={onSearch} />
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
        <ExamPagination page={data.paginacion.current_page} lastPage={data.paginacion.last_page} onChange={onPage} label="estudiantes" />
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
