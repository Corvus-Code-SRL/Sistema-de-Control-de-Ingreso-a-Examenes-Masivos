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
import { clearAttendanceFilters, ExamAttendanceRoster } from '../components/ExamAttendanceRoster'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { examsService } from '../services/examsService'
import type { ExamAttendanceExam } from '../types/examAttendance.types'

const TITLE = 'Estudiantes ingresados'
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
      if (examId) next.set('examen', examId)
      else next.delete('examen')
      return next
    })
  }

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: 'Exámenes' }, { label: TITLE }]}>
      <PageHeader title={TITLE} />

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
        </>
      )}
    </AppShell>
  )
}

function SelectedAttendance({ exam, onRefreshExams }: { exam: ExamAttendanceExam; onRefreshExams: () => void }) {
  const { area } = useCurrentUser()
  const { data, status, error, reload } = useAsyncResource(
    (signal) => examsService.getAttendance(exam.id_examen, signal), [exam.id_examen]
  )
  const unavailable = error?.status === 409
  const detailRoom = status === 'success' && data
    ? data.ambientes.find((room) => room.id_ambiente === data.id_ambiente_asignado)?.nro_aula
    : undefined
  const assignedRoom = area === 'auxiliar' ? detailRoom : detailRoom ?? exam.ambiente?.nro_aula

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
      {status === 'loading' && <LoadingState rows={3} label="Cargando estudiantes del examen" />}
      {unavailable && (
        <StatePanel role="status" tone="neutral" icon={ClipboardList} title="La consulta del examen ha terminado">
          <p>{error.message}</p>
          <Button variant="outline" onClick={onRefreshExams}>Actualizar exámenes</Button>
        </StatePanel>
      )}
      {status === 'error' && error && !unavailable && (
        <ErrorState error={error} onRetry={error.isForbidden || error.isNotFound ? undefined : reload} />
      )}
      {status === 'success' && data && <ExamAttendanceRoster detail={data} />}
    </>
  )
}
