import { CalendarClock, Clock, DoorOpen } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { useMyAssistantExams } from '../hooks/useMyAssistantExams'
import type { AssistantExam, AssistantExamStatus } from '../types/assistant.types'

const STATUS_LABELS: Record<AssistantExamStatus, { label: string; className: string }> = {
  PROGRAMADO: { label: 'Programado', className: 'bg-ok-soft text-ok-fg' },
  EN_INGRESO: { label: 'Control de ingreso abierto', className: 'bg-info-soft text-info' },
  EN_CURSO: { label: 'En curso', className: 'bg-info-soft text-info' },
}

/**
 * Exámenes que controla el auxiliar y el ambiente que el docente le asignó
 * (HU-09, mockup 9.6). Es de solo lectura: el auxiliar nunca elige ni cambia
 * su ambiente.
 */
export function MyAssignmentsPage() {
  const { status, data, error, reload } = useMyAssistantExams()
  const exams = data ?? []

  return (
    <AppShell mobileTitle="Mis exámenes" breadcrumbs={[{ label: 'Mis exámenes' }]}>
      <PageHeader title="Mis exámenes" subtitle="Exámenes en los que usted controla el ingreso." />

      {status === 'loading' && <LoadingState rows={3} label="Cargando sus exámenes" />}

      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={CalendarClock}
          title="No tiene exámenes por controlar"
          description="Cuando un docente lo habilite como auxiliar de un examen, aparecerá aquí con el ambiente que le asigne."
        />
      )}

      {status === 'success' && exams.length > 0 && (
        <ul className="space-y-3">
          {exams.map((exam) => (
            <li key={exam.id_examen}>
              <AssistantExamCard exam={exam} />
            </li>
          ))}
        </ul>
      )}
    </AppShell>
  )
}

function AssistantExamCard({ exam }: { exam: AssistantExam }) {
  const { label, className } = STATUS_LABELS[exam.estado]

  return (
    <article className="space-y-2 rounded-xl border bg-card p-4 shadow-xs">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="text-sm font-semibold tabular-nums">
          {formatDate(exam.fecha)} · {formatSchedule(exam)}
        </span>
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>{label}</span>
      </div>

      <h2 className="sciem-h3">{exam.nombre_examen}</h2>
      {exam.materia && <p className="text-sm text-muted-foreground">{exam.materia}</p>}

      {exam.ambiente ? (
        <p className="flex items-start gap-2 text-sm text-ok-fg">
          <DoorOpen className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>
            Usted controla en <strong>{exam.ambiente.nro_aula}</strong>, asignado por el docente.
          </span>
        </p>
      ) : (
        <p className="flex items-start gap-2 text-sm text-warn-fg">
          <Clock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>Aún sin ambiente asignado. El docente lo asignará antes de abrir el control.</span>
        </p>
      )}
    </article>
  )
}

/**
 * La fecha llega como YYYY-MM-DD, sin zona. Se arma a medianoche local: con
 * `new Date('YYYY-MM-DD')` el navegador la toma en UTC y en Bolivia mostraría el día anterior.
 */
function formatDate(value: string | null): string {
  if (!value) return 'Sin fecha'

  return new Date(`${value}T00:00:00`).toLocaleDateString('es-BO', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })
}

function formatSchedule(exam: AssistantExam): string {
  return exam.hora_inicio && exam.hora_fin
    ? `${exam.hora_inicio}–${exam.hora_fin}`
    : 'Horario pendiente'
}