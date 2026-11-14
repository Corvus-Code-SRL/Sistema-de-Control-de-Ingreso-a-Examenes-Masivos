import { useState } from 'react'
import { ClipboardList } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { useAsyncResource } from '@/hooks/useAsyncResource'
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
  const [selectedId, setSelectedId] = useState('')
  const exams = data ?? []
  const selected = exams.find((exam) => String(exam.id_examen) === selectedId)

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
                onValueChange={setSelectedId}
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

          {selected && (
            <Card>
              <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <h2 className="sciem-h3 min-w-0 break-words">{selected.nombre_examen}</h2>
                  <ExamStatusBadge status={selected.estado} />
                </div>
                {selected.materia && <p className="break-words text-muted-foreground">{selected.materia.nombre}</p>}
              </CardHeader>
              <CardContent className="space-y-1 text-muted-foreground">
                <p>{scheduleOf(selected)}</p>
                {selected.grupos.length > 0 && <p>Grupos: {selected.grupos.map((group) => group.num_grupo).join(', ')}</p>}
                {selected.ambiente && <p>Ambiente: {selected.ambiente.nro_aula}</p>}
              </CardContent>
            </Card>
          )}
        </>
      )}
    </AppShell>
  )
}
