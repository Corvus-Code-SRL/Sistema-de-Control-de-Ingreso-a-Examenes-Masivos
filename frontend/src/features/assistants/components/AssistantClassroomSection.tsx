import { AlertTriangle, DoorOpen, Info, Lock, Users } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Toast } from '@/components/ui/toast'
import { useExamAssistants } from '../hooks/useExamAssistants'
import type { ExamAssistant, ExamAssistantsData, ExamClassroom } from '../types/assistant.types'
import { AssistantClassroomRow } from './AssistantClassroomRow'

interface AssistantClassroomSectionProps {
  examId: number
}

/**
 * Sección «Personal» del detalle del examen (HU-09): el docente asigna a cada
 * auxiliar habilitado uno de los ambientes del examen.
 *
 * Si todavía se puede editar lo decide el backend según el estado del examen;
 * la vista no calcula horarios por su cuenta.
 */
export function AssistantClassroomSection({ examId }: AssistantClassroomSectionProps) {
  const {
    status,
    data,
    error,
    reload,
    assign,
    savingUserId,
    notice,
    dismissNotice,
    assignError,
    lockedMessage,
    dismissLocked,
  } = useExamAssistants(examId)

  return (
    <section
      aria-labelledby="exam-assistants-title"
      className="space-y-4 rounded-xl border bg-card p-6 shadow-xs"
    >
      <header className="space-y-1">
        <h2 id="exam-assistants-title" className="sciem-h3">
          Personal · ambientes de los auxiliares
        </h2>
        <p className="text-sm text-muted-foreground">
          Cada auxiliar controla el ingreso en el ambiente que usted le asigne. Solo aparecen los
          ambientes de este examen.
        </p>
      </header>

      {status === 'loading' && <LoadingState rows={3} label="Cargando los auxiliares" />}

      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

      {status === 'success' && data && data.auxiliares.length === 0 && (
        <EmptyState
          icon={Users}
          title="Este examen no tiene auxiliares habilitados"
          description="Cuando habilite auxiliares de sus grupos para este examen, podrá asignarles un ambiente aquí."
        />
      )}

      {status === 'success' && data && data.auxiliares.length > 0 && (
        <>
          {data.editable ? (
            <Notice
              icon={Info}
              className="border-info-border bg-info-soft text-info"
              title="Cada cambio se guarda al momento"
              body="Puede asignar y reasignar ambientes hasta que se abra el control de ingreso."
            />
          ) : (
            <Notice
              icon={Lock}
              className="border-warn-border bg-warn-soft text-warn-fg"
              {...readOnlyNotice(data.estado)}
            />
          )}

          {assignError && (
            <p
              role="alert"
              className="rounded-xl border border-danger/20 bg-danger-soft p-4 text-sm font-medium text-danger-fg"
            >
              {assignError}
            </p>
          )}

          <ul className="divide-y overflow-hidden rounded-lg border">
            {data.auxiliares.map((assistant) => (
              <AssistantClassroomRow
                key={assistant.id_usuario}
                assistant={assistant}
                classrooms={data.ambientes}
                editable={data.editable}
                saving={savingUserId === assistant.id_usuario}
                disabled={savingUserId !== null}
                onAssign={(target, classroomId) => void assign(target, classroomId)}
              />
            ))}
          </ul>

          <AssignmentSummary assistants={data.auxiliares} classrooms={data.ambientes} />
        </>
      )}

      <Dialog open={lockedMessage !== null} onOpenChange={(open) => !open && dismissLocked()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>No se guardó la asignación</DialogTitle>
            <DialogDescription>{lockedMessage}</DialogDescription>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">La asignación anterior se conserva.</p>
          <DialogFooter>
            <Button onClick={dismissLocked}>Entendido</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {notice && (
        <Toast title={notice.title} description={notice.description} onClose={dismissNotice} />
      )}
    </section>
  )
}

interface NoticeProps {
  icon: LucideIcon
  className: string
  title: string
  body: string
}

function Notice({ icon: Icon, className, title, body }: NoticeProps) {
  return (
    <div className={`flex items-start gap-3 rounded-xl border p-4 text-sm ${className}`}>
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div>
        <p className="font-semibold">{title}</p>
        <p>{body}</p>
      </div>
    </div>
  )
}

/** Aviso de solo lectura según el estado del examen que informa el backend. */
function readOnlyNotice(status: ExamAssistantsData['estado']): Pick<NoticeProps, 'title' | 'body'> {
  return status === 'CANCELADO'
    ? { title: 'El examen fue cancelado', body: 'Las asignaciones ya no se modifican.' }
    : { title: 'El control de ingreso ya se abrió', body: 'Las asignaciones quedaron fijas.' }
}

interface AssignmentSummaryProps {
  assistants: ExamAssistant[]
  classrooms: ExamClassroom[]
}

/** Resumen de auxiliares por ambiente. Solo informa: la asignación se hace en cada fila. */
function AssignmentSummary({ assistants, classrooms }: AssignmentSummaryProps) {
  const unassigned = assistants.filter((assistant) => assistant.id_ambiente === null).length

  return (
    <p className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground">
      {classrooms.map((classroom) => {
        const count = assistants.filter(
          (assistant) => assistant.id_ambiente === classroom.id_ambiente
        ).length

        return (
          <span key={classroom.id_ambiente} className="inline-flex items-center gap-1.5">
            <DoorOpen className="size-4" aria-hidden="true" />
            <span className="font-medium text-foreground">{classroom.nro_aula}</span>·{' '}
            {count} {count === 1 ? 'auxiliar' : 'auxiliares'}
          </span>
        )
      })}

      {unassigned > 0 && (
        <span className="inline-flex items-center gap-1.5 text-warn-fg">
          <AlertTriangle className="size-4" aria-hidden="true" />
          {unassigned} sin ambiente
        </span>
      )}
    </p>
  )
}
