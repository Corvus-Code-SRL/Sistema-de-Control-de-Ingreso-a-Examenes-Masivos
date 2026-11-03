import { useEffect, useRef, useState } from 'react'
import { FileWarning } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ApiError } from '@/lib/api-client'
import { ExamPagination } from '../components/ExamPagination'
import { ExamSelection } from '../components/ExamSelection'
import { ExamStudentFilters } from '../components/ExamStudentFilters'
import { loadIncidentReportContext } from '../components/incidentReportContext'
import { REPORT_INCIDENT_MESSAGES, REPORT_INCIDENT_STATE_LABELS } from '../components/reportIncidentMessages'
import { ReportIncidentDialog } from '../components/ReportIncidentDialog'
import { useExamSituationWorkspace } from '../hooks/useExamSituationWorkspace'
import { incidentService } from '../services/incidentService'
import { formatSituationTime } from '../types/exam-situation.types'
import type { ExamIncidentContext } from '../types/incident.types'

const fetchIncidentExams = (page: number, signal?: AbortSignal) => incidentService.exams(page, signal)

/**
 * Acceso «Reportar incidencia» del menú (HU-042): elige el examen En curso, busca a un estudiante
 * presente y abre el mismo formulario que la acción «Reportar» de la fila de En curso.
 */
export function ReportIncidentPage() {
  return (
    <AppShell mobileTitle="Reportar incidencia" breadcrumbs={[{ label: 'Reportar incidencia' }]}>
      <PageHeader title="Reportar incidencia"
        subtitle="Reporte de faltas de estudiantes presentes mientras el examen está en curso." />
      <ExamSelection fetchExams={fetchIncidentExams} selectId="incident-exam" icon={FileWarning}
        emptyTitle={REPORT_INCIDENT_MESSAGES.emptyTitle}
        emptyDescription={REPORT_INCIDENT_MESSAGES.emptyDescription}
        stateLabels={REPORT_INCIDENT_STATE_LABELS} autoSelectSingle>
        {(examId) => <IncidentWorkspaceFilters key={examId} examId={examId} />}
      </ExamSelection>
    </AppShell>
  )
}

function IncidentWorkspaceFilters({ examId }: { examId: number }) {
  const [roomId, setRoomId] = useState<number | undefined>()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  return <IncidentWorkspace key={`${examId}-${roomId ?? 'all'}-${search}-${page}`}
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

function IncidentWorkspace({ examId, roomId, search, page, onRoom, onSearch, onPage }: WorkspaceProps) {
  const resource = useExamSituationWorkspace(examId, roomId, search, page, incidentService.workspace)
  const [draft, setDraft] = useState(search)
  const [context, setContext] = useState<ExamIncidentContext | null>(null)
  const [message, setMessage] = useState('')
  const [opening, setOpening] = useState(false)
  const openingRequest = useRef<AbortController | null>(null)
  useEffect(() => () => { openingRequest.current?.abort() }, [])

  async function openReport(studentId: number) {
    if (openingRequest.current) return
    const controller = new AbortController()
    openingRequest.current = controller
    setOpening(true)
    setMessage('')
    try {
      const loaded = await loadIncidentReportContext(examId, studentId, controller.signal)
      if (controller.signal.aborted) return
      if (!loaded) {
        setMessage('El examen o el estudiante cambió. Se actualizó la vista; seleccione nuevamente al estudiante.')
        resource.reload()
        return
      }
      setContext(loaded.context)
    } catch (cause) {
      if (!controller.signal.aborted) {
        setMessage(cause instanceof ApiError && !cause.isServerError
          ? cause.message : 'No se pudo cargar el contexto. Intente nuevamente.')
        resource.reload()
      }
    } finally {
      if (!controller.signal.aborted) setOpening(false)
      openingRequest.current = null
    }
  }

  const data = resource.data
  const canReport = !!data && !resource.error
  const effectiveRoom = data?.ambiente_control ?? roomId
  return <>
    {message && <p role="status" className="text-sm">{message}</p>}
    {resource.error && <ErrorState error={resource.error} onRetry={resource.reload} />}
    {!data && !resource.error && <LoadingState rows={3} label="Cargando estudiantes presentes" />}
    {data && <Card className="space-y-4 p-4">
      <ExamStudentFilters idPrefix="incident" rooms={data.ambientes} controlRoom={data.ambiente_control}
        effectiveRoom={effectiveRoom} draft={draft} onDraft={setDraft} onRoom={onRoom} onSearch={onSearch} />
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-base font-semibold">Estudiantes presentes ({data.paginacion.total})</h2>
        <Button type="button" variant="outline" size="sm" onClick={resource.reload}>Actualizar</Button>
      </div>
      <p className="text-xs text-muted-foreground">
        {resource.error ? 'Datos sin actualizar desde' : 'Última actualización:'} {formatSituationTime(data.hora_servidor)}
      </p>
      {!data.estudiantes.length && <p>{REPORT_INCIDENT_MESSAGES.noStudents}</p>}
      <ul className="space-y-3" aria-label="Estudiantes presentes">
        {data.estudiantes.map((student) => {
          const total = student.total_incidencias ?? 0
          return <li key={student.id_estudiante}
            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
            <div className="min-w-0 space-y-1 break-words">
              <p className="font-medium">{student.nombre_completo}</p>
              <p className="text-sm text-muted-foreground">{student.cod_sis} · Ambiente {student.ambiente.nro_aula}</p>
              <p className="text-sm">{student.estado_ingreso === 'CON_RETRASO' ? 'Presente con retraso' : 'Presente'}</p>
              {total > 0 && <p className="text-sm font-medium text-warn-fg">
                {total === 1 ? 'Incidencia reportada' : `${total} incidencias`}
              </p>}
            </div>
            <Button type="button" variant="outline" disabled={!canReport || opening}
              onClick={() => { void openReport(student.id_estudiante) }}>Reportar</Button>
          </li>
        })}
      </ul>
      <ExamPagination page={data.paginacion.current_page} lastPage={data.paginacion.last_page}
        onChange={onPage} label="estudiantes" />
    </Card>}
    {context && <ReportIncidentDialog context={context} readOnly={!canReport}
      onClose={() => { setContext(null); resource.reload() }}
      onReported={() => {
        setContext(null)
        setMessage(REPORT_INCIDENT_MESSAGES.sent)
        resource.reload()
      }} />}
  </>
}
