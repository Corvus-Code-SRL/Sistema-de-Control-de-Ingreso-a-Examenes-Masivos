import { Clock } from 'lucide-react'
import { CONTROLLER_ROLE } from '../types/late-entry.types'
import { useLateEntryRequests } from '../hooks/useLateEntryRequests'
import { LateEntryAnnouncement } from './LateEntryAnnouncement'
import { LateEntryRequestActions } from './LateEntryRequestActions'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryRequestNoticeProps {
  examId: number
}

/** Aviso del docente en En curso: las solicitudes pendientes, para autorizarlas o rechazarlas ahí mismo. */
export function LateEntryRequestNotice({ examId }: LateEntryRequestNoticeProps) {
  const requests = useLateEntryRequests(examId, CONTROLLER_ROLE.DOCENTE, true)

  return (
    <div className="space-y-3">
      <LateEntryAnnouncement
        message={requests.announcement}
        error={requests.actionError}
        onClose={requests.clearAnnouncement}
      />
      {requests.pending.length > 0 && (
        <section
          aria-labelledby="late-entry-notice-title"
          className="rounded-xl border border-warn-border bg-warn-soft p-4 shadow-sm"
        >
          <h2 id="late-entry-notice-title" className="flex items-center gap-2 font-semibold text-warn-fg">
            <Clock className="size-4 shrink-0" aria-hidden="true" />
            {LATE_ENTRY_MESSAGES.noticeTitle}
          </h2>
          <ul className="mt-3 space-y-2">
            {requests.pending.map((request) => (
              <li
                key={request.id_solicitud}
                className="flex flex-col gap-3 rounded-lg border bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <p className="min-w-0 break-words text-sm">
                  <span className="font-medium">{request.estudiante.nombre_completo}</span>
                  {' · '}{request.ambiente.nro_aula}
                  {' · '}<span className="sciem-tnum">{request.hora_solicitud}</span>
                  {' · '}{LATE_ENTRY_MESSAGES.requestedBy(request.solicitante.nombre_completo)}
                </p>
                <LateEntryRequestActions
                  request={request}
                  busy={requests.busyId !== null}
                  onApprove={(id) => void requests.approve(id)}
                  onReject={(id) => void requests.reject(id)}
                />
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}
