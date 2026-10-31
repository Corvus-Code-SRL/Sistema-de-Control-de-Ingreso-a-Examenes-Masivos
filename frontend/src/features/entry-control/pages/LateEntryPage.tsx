import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Lock } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { StatePanel } from '@/components/common/StatePanel'
import { AppShell } from '@/components/layout/AppShell'
import { Label } from '@/components/ui/label'
import { EXAM_STATUS, examInProgressService, type ExamInProgress } from '@/features/exams'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { IdentificationForm } from '../components/IdentificationForm'
import { LateEntryAnnouncement } from '../components/LateEntryAnnouncement'
import { LateEntryNextButton } from '../components/LateEntryNextButton'
import { LateEntryRequestActions } from '../components/LateEntryRequestActions'
import { LateEntryRequestList } from '../components/LateEntryRequestList'
import { LateEntryWaitingStatus } from '../components/LateEntryWaitingStatus'
import {
  ASSISTANT_LATE_ENTRY_ACTIONS, LATE_ENTRY_MESSAGES, TEACHER_LATE_ENTRY_ACTIONS,
} from '../components/lateEntryMessages'
import { VerificationResult } from '../components/VerificationResult'
import { useLateEntryRequests } from '../hooks/useLateEntryRequests'
import { useLateEntryVerification } from '../hooks/useLateEntryVerification'
import { LATE_ENTRY_EXAMS_PATH, inProgressPath } from '../paths'
import { CONTROLLER_ROLE } from '../types/late-entry.types'

/** Campo SIS de IdentificationForm, al que vuelve el foco con «Siguiente estudiante». */
const SIS_INPUT_ID = 'entry-sis'
const ROOM_SELECT_ID = 'late-entry-room'

/** Sin sugerencias (las apaga suggestions={false}) no hay nada que elegir de una lista. */
function ignoreSuggestion() {}

export function LateEntryPage() {
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)

  if (!Number.isInteger(id) || id <= 0) {
    return (
      <AppShell mobileTitle={LATE_ENTRY_MESSAGES.title} breadcrumbs={[{ label: LATE_ENTRY_MESSAGES.title }]}>
        <p role="alert">{LATE_ENTRY_MESSAGES.invalidExam}</p>
      </AppShell>
    )
  }

  return <LateEntryContent key={id} examId={id} />
}

function LateEntryContent({ examId }: { examId: number }) {
  const exam = useAsyncResource((signal) => examInProgressService.get(examId, signal), [examId])
  const data = exam.data

  return (
    <AppShell
      mobileTitle={LATE_ENTRY_MESSAGES.title}
      mobileSubtitle={data?.nombre_examen}
      breadcrumbs={[
        { label: LATE_ENTRY_MESSAGES.examsTitle, to: LATE_ENTRY_EXAMS_PATH },
        { label: data?.nombre_examen ?? LATE_ENTRY_MESSAGES.title },
      ]}
    >
      <PageHeader
        title={LATE_ENTRY_MESSAGES.title}
        subtitle={data ? [data.nombre_examen, data.materia].filter(Boolean).join(' · ') : undefined}
        backTo={inProgressPath(examId)}
        backLabel={LATE_ENTRY_MESSAGES.backToInProgress}
      />

      {!data && exam.status === 'loading' && <LoadingState rows={3} label={LATE_ENTRY_MESSAGES.loadingExam} />}
      {!data && exam.status === 'error' && exam.error && <ErrorState error={exam.error} onRetry={exam.reload} />}
      {data && <LateEntryWorkspace exam={data} onConflict={exam.reload} />}
    </AppShell>
  )
}

function LateEntryWorkspace({ exam, onConflict }: { exam: ExamInProgress; onConflict: () => void }) {
  const isTeacher = exam.rol_controlador === CONTROLLER_ROLE.DOCENTE
  const open = exam.estado === EXAM_STATUS.EN_CURSO
  const controlRoomId = isTeacher ? null : exam.id_ambiente_asignado
  const verification = useLateEntryVerification(exam.id_examen, controlRoomId, onConflict)
  const requests = useLateEntryRequests(exam.id_examen, exam.rol_controlador, true)
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null)
  const focusSisOnReset = useRef(false)

  const { result, sentRequest, busy } = verification
  const { track } = requests

  useEffect(() => { setSelectedRoomId(null) }, [result])

  useEffect(() => {
    if (sentRequest) track(sentRequest)
  }, [sentRequest, track])

  useEffect(() => {
    if (result !== null || !focusSisOnReset.current) return
    focusSisOnReset.current = false
    document.getElementById(SIS_INPUT_ID)?.focus()
  }, [result])

  function next() {
    focusSisOnReset.current = true
    verification.reset()
  }

  async function resolveInline(requestId: number, approve: boolean) {
    const done = approve ? await requests.approve(requestId) : await requests.reject(requestId)
    if (done) next()
  }

  const roomId = selectedRoomId ?? result?.ambiente_asignado?.id_ambiente ?? exam.ambientes[0]?.id_ambiente ?? null
  const pending = result?.solicitud_pendiente ?? null
  const ownRequest = sentRequest
    ? requests.requests?.find((item) => item.id_solicitud === sentRequest.id_solicitud) ?? sentRequest
    : null
  const waitingRequest = ownRequest ?? pending
  const missingRoom = !isTeacher && controlRoomId === null

  function footer() {
    if (!isTeacher && waitingRequest) {
      return <LateEntryWaitingStatus request={waitingRequest} onNext={next} />
    }
    if (isTeacher && pending) {
      return (
        <div className="space-y-2">
          <LateEntryRequestActions
            request={pending}
            busy={requests.busyId !== null}
            onApprove={(id) => void resolveInline(id, true)}
            onReject={(id) => void resolveInline(id, false)}
          />
          <div className="flex"><LateEntryNextButton onNext={next} /></div>
        </div>
      )
    }

    return undefined
  }

  function extra() {
    if (isTeacher && pending) {
      return (
        <p className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">
          {LATE_ENTRY_MESSAGES.pendingInline}{' '}
          <span className="sciem-tnum">{pending.hora_solicitud}</span>
          {' · '}{pending.ambiente.nro_aula}
          {' · '}{LATE_ENTRY_MESSAGES.requestedBy(pending.solicitante.nombre_completo)}
        </p>
      )
    }
    if (isTeacher && result?.autorizado) {
      return (
        <div className="space-y-1.5">
          <Label htmlFor={ROOM_SELECT_ID}>{LATE_ENTRY_MESSAGES.roomField}</Label>
          <FormSelect
            id={ROOM_SELECT_ID}
            value={roomId === null ? undefined : String(roomId)}
            placeholder={LATE_ENTRY_MESSAGES.roomPlaceholder}
            options={exam.ambientes.map((room) => ({ value: String(room.id_ambiente), label: room.nro_aula }))}
            onValueChange={(value) => setSelectedRoomId(Number(value))}
            disabled={busy !== null}
          />
        </div>
      )
    }

    return null
  }

  const list = (
    <LateEntryRequestList
      title={isTeacher ? LATE_ENTRY_MESSAGES.listTitle : LATE_ENTRY_MESSAGES.listTitleOwn}
      requests={requests.requests}
      error={requests.error}
      showRequester={isTeacher}
      actions={isTeacher && open ? {
        busyId: requests.busyId,
        onApprove: (id) => void requests.approve(id),
        onReject: (id) => void requests.reject(id),
      } : undefined}
    />
  )

  if (!open) {
    return (
      <div className="space-y-5">
        <StatePanel tone="neutral" icon={Lock} title={LATE_ENTRY_MESSAGES.closedTitle}>
          {LATE_ENTRY_MESSAGES.closedDescription}
        </StatePanel>
        {list}
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <LateEntryAnnouncement
        message={verification.notice ?? requests.announcement}
        error={verification.error ?? requests.actionError}
        onClose={() => { verification.setNotice(null); requests.clearAnnouncement() }}
      />
      {missingRoom && (
        <p role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger-fg">
          {LATE_ENTRY_MESSAGES.noRoom}
        </p>
      )}
      <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <div className={result ? 'hidden lg:block' : ''}>
          <IdentificationForm
            examId={exam.id_examen}
            sis={verification.sis}
            ci={verification.ci}
            disabled={missingRoom || busy !== null}
            busy={busy === 'verify'}
            onSisChange={verification.changeSis}
            onCiChange={verification.changeCi}
            onSelect={ignoreSuggestion}
            onVerify={() => void verification.verify()}
            suggestions={false}
          />
        </div>
        <div className="min-w-0 space-y-5">
          {result ? (
            <VerificationResult
              result={result}
              presentedCi={verification.ci}
              subjectName={exam.materia}
              busy={busy !== null}
              actions={isTeacher ? TEACHER_LATE_ENTRY_ACTIONS : ASSISTANT_LATE_ENTRY_ACTIONS}
              onConfirm={() => {
                if (!isTeacher) {
                  void verification.request()
                } else if (roomId !== null) {
                  // Si el registro sale bien el resultado se cierra y el foco vuelve al SIS.
                  focusSisOnReset.current = true
                  void verification.register(roomId)
                }
              }}
              onReject={next}
              onNext={next}
              extra={extra()}
              footer={footer()}
            />
          ) : list}
        </div>
      </div>
    </div>
  )
}
