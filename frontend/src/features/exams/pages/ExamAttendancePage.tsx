import { useEffect } from 'react'
import { ClipboardList } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { StatePanel } from '@/components/common/StatePanel'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { useCurrentUser } from '@/features/auth'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { ExamAttendanceRoster } from '../components/ExamAttendanceRoster'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { useExamAttendanceDetail } from '../hooks/useExamAttendanceDetail'
import { examsService } from '../services/examsService'
import type { ExamAttendanceExam } from '../types/examAttendance.types'
import { attendanceQuery, clearAttendanceFilters } from '../utils/attendanceFilters'

const TITLE = 'Estado de ingreso de estudiantes'
const SUBTITLE = 'Consulta los estudiantes registrados, verifica quiénes están pendientes de registro y revisa su estado durante el examen.'
const SELECT_ID = 'attendance-exam'
const examDate = new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeZone: 'UTC' })

function scheduleOf(exam: ExamAttendanceExam): string {
  const date = examDate.format(new Date(`${exam.fecha}T00:00:00Z`))
  const time = exam.hora_fin ? `${exam.hora_inicio}–${exam.hora_fin}` : exam.hora_inicio
  return `${date} · ${time}`
}

export function ExamAttendancePage() {
  const { data, status, error, reload } = useAsyncResource(examsService.listAttendanceExams, [])
  const [params, setParams] = useSearchParams()
  const selectedId = params.get('examen') ?? ''
  const exams = data ?? []
  const selected = exams.find((exam) => String(exam.id_examen) === selectedId)

  function selectExam(examId: string) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      clearAttendanceFilters(next)
      next.delete('vista')
      if (examId) next.set('examen', examId)
      else next.delete('examen')
      return next
    })
  }

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: 'Exámenes' }, { label: TITLE }]}>
      <PageHeader title={TITLE} subtitle={SUBTITLE} />

      {status === 'loading' && <LoadingState rows={2} label="Cargando exámenes disponibles" />}
      {status === 'error' && error && <ErrorState error={error} onRetry={error.isForbidden ? undefined : reload} />}

      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={ClipboardList}
          title="No tiene exámenes con ingreso abierto ni en curso"
          description="Cuando haya un examen disponible para usted, aparecerá aquí."
        />
      )}

      {status === 'success' && exams.length > 0 && (
        <>
          <Card>
            <CardContent className="min-w-0 space-y-2">
              <Label htmlFor={SELECT_ID}>Examen</Label>
              <FormSelect
                id={SELECT_ID}
                value={selected ? selectedId : ''}
                onValueChange={selectExam}
                placeholder="Seleccione un examen"
                className="min-w-0 [&_[data-slot=select-value]]:block [&_[data-slot=select-value]]:truncate"
                contentClassName="w-(--radix-select-trigger-width) min-w-0 max-w-(--radix-select-content-available-width) [&_[data-slot=select-item]]:wrap-anywhere"
                options={exams.map((exam) => ({
                  value: String(exam.id_examen),
                  label: `${exam.nombre_examen} · ${scheduleOf(exam)} · #${exam.id_examen}`,
                }))}
              />
            </CardContent>
          </Card>

          {selected && <SelectedAttendance key={selected.id_examen} exam={selected} onRefreshExams={() => {
            selectExam('')
            reload()
          }} />}
          {!selected && selectedId && (
            <StatePanel tone="neutral" icon={ClipboardList} title="El examen seleccionado no está disponible">
              <p>Seleccione un examen del catálogo.</p>
            </StatePanel>
          )}
          {!selectedId && (
            <EmptyState icon={ClipboardList} title="Seleccione un examen"
              description="Elija un examen para consultar el estado de sus estudiantes." />
          )}
        </>
      )}
    </AppShell>
  )
}

function SelectedAttendance({ exam, onRefreshExams }: { exam: ExamAttendanceExam; onRefreshExams: () => void }) {
  const { area } = useCurrentUser()
  const [params, setParams] = useSearchParams()
  const { data, isUpdating, error, pollNotice, reload } = useExamAttendanceDetail(
    exam.id_examen, attendanceQuery(params, area !== 'auxiliar')
  )
  const unavailable = error !== null && (error.status === 409 || error.isNotFound)
  const detailRoom = data?.ambientes.find((room) => room.id_ambiente === data.id_ambiente_asignado)?.nro_aula
  const assignedRoom = area === 'auxiliar' ? detailRoom : detailRoom ?? exam.ambiente?.nro_aula

  useEffect(() => {
    if (!data || isUpdating || !params.has('page') || Number(params.get('page')) === data.paginacion.current_page) return
    setParams((current) => {
      const next = new URLSearchParams(current)
      if (data.paginacion.current_page === 1) next.delete('page')
      else next.set('page', String(data.paginacion.current_page))
      return next
    }, { replace: true })
  }, [data, isUpdating, params, setParams])

  return (
    <>
      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-2">
            <h2 className="sciem-h3 min-w-0 break-words">{exam.nombre_examen}</h2>
            {!unavailable && <ExamStatusBadge status={data?.estado ?? exam.estado} />}
          </div>
          {exam.materia && <p className="break-words text-muted-foreground">{exam.materia.nombre}</p>}
        </CardHeader>
        <CardContent className="space-y-1 text-muted-foreground">
          <p>{scheduleOf(exam)}</p>
          {exam.grupos.length > 0 && <p>Grupos: {exam.grupos.map((group) => group.num_grupo).join(', ')}</p>}
          {assignedRoom && <p>Ambiente: {assignedRoom}</p>}
        </CardContent>
      </Card>
      {!data && !error && !pollNotice && <LoadingState rows={3} label="Cargando estudiantes del examen" />}
      {pollNotice && (
        <p role="status" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">
          {pollNotice}
        </p>
      )}
      {unavailable && (
        <StatePanel role="status" tone="neutral" icon={ClipboardList} title="La consulta del examen ha terminado">
          <p>{error.message}</p>
          <Button variant="outline" onClick={onRefreshExams}>Actualizar exámenes</Button>
        </StatePanel>
      )}
      {error && !unavailable && (
        <>
          <ErrorState error={error} onRetry={error.isForbidden || error.isUnauthorized ? undefined : reload} />
          {error.isForbidden && <Button variant="outline" onClick={onRefreshExams}>Actualizar exámenes</Button>}
        </>
      )}
      {data && <ExamAttendanceRoster detail={data} isUpdating={isUpdating} />}
    </>
  )
}
