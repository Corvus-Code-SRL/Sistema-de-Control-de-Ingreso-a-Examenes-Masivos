import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Clock, Lock } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { StatePanel } from '@/components/common/StatePanel'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { EXAM_STATUS, examInProgressService, type ExamInProgress } from '@/features/exams'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { IdentificationForm } from '../components/IdentificationForm'
import { LateEntryAnnouncement } from '../components/LateEntryAnnouncement'
import { LateEntryNextButton } from '../components/LateEntryNextButton'
import { LateEntryOtherExamDialog } from '../components/LateEntryOtherExamDialog'
import { LateEntryOtherExamNotice } from '../components/LateEntryOtherExamNotice'
import { LateEntryRequestActions } from '../components/LateEntryRequestActions'
import { LateEntryRequestList } from '../components/LateEntryRequestList'
import { LateEntryWaitingStatus } from '../components/LateEntryWaitingStatus'
import {
  ASSISTANT_LATE_ENTRY_ACTIONS, ASSISTANT_OTHER_EXAM_ACTIONS, LATE_ENTRY_MESSAGES, TEACHER_LATE_ENTRY_ACTIONS,
  TEACHER_OTHER_EXAM_ACTIONS,
} from '../components/lateEntryMessages'
import { VerificationResult } from '../components/VerificationResult'
import { useLateEntryDeadline } from '../hooks/useLateEntryDeadline'
import { useLateEntryRequests } from '../hooks/useLateEntryRequests'
import { useLateEntryVerification } from '../hooks/useLateEntryVerification'
import { LATE_ENTRY_SEARCH } from '../services/lateEntryService'
import { LATE_ENTRY_EXAMS_PATH } from '../paths'
import { CONTROLLER_ROLE } from '../types/late-entry.types'

/** Campo SIS de IdentificationForm, al que vuelve el foco con «Siguiente estudiante». */
const SIS_INPUT_ID = 'entry-sis'
const ROOM_SELECT_ID = 'late-entry-room'

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
  // El examen se carga una vez: solo cambia de estado al finalizar, y eso lo avisa un 409 o la
  // vuelta a la pestaña. Las solicitudes, que sí necesitan tiempo real, tienen su propia consulta.
  const exam = useAsyncResource((signal) => examInProgressService.get(examId, signal), [examId])
  const data = exam.data
  const { reload } = exam

  useEffect(() => {
    function reloadWhenVisible() {
      if (!document.hidden) reload()
    }

    document.addEventListener('visibilitychange', reloadWhenVisible)
    return () => document.removeEventListener('visibilitychange', reloadWhenVisible)
  }, [reload])

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
        backTo={LATE_ENTRY_EXAMS_PATH}
        backLabel={LATE_ENTRY_MESSAGES.backToList}
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
  // El ingreso con retraso termina en el fin programado, aunque el examen siga EN_CURSO hasta su cierre.
  const timeOver = useLateEntryDeadline(exam.fin_programado_en, exam.hora_servidor_en)
  const controlRoomId = isTeacher ? null : exam.id_ambiente_asignado
  const verification = useLateEntryVerification(exam.id_examen, controlRoomId, onConflict)
  const requests = useLateEntryRequests(exam.id_examen, exam.rol_controlador, true)
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null)
  const [confirmingOtherExam, setConfirmingOtherExam] = useState(false)
  const focusSisOnReset = useRef(false)

  const { result, sentRequest, busy } = verification
  const { track } = requests

  useEffect(() => {
    setSelectedRoomId(null)
    setConfirmingOtherExam(false)
  }, [result])

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

  const warning = result?.autorizado ? result.otro_examen : null

  async function resolveInline(requestId: number, approve: boolean) {
    const done = approve
      ? await requests.approve(requestId, warning !== null)
      : await requests.reject(requestId)
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
            onApprove={(request) => void resolveInline(request.id_solicitud, true)}
            onReject={(id) => void resolveInline(id, false)}
          />
          <div className="flex"><LateEntryNextButton onNext={next} /></div>
        </div>
      )
    }

    return undefined
  }

  function register(confirmOtherExam: boolean) {
    if (roomId === null) return
    // Si el registro sale bien el resultado se cierra y el foco vuelve al SIS.
    focusSisOnReset.current = true
    void verification.register(roomId, confirmOtherExam)
  }

  function confirm() {
    if (!isTeacher) {
      void verification.request(warning !== null)
    } else if (warning) {
      setConfirmingOtherExam(true)
    } else {
      register(false)
    }
  }

  function extra() {
    const notice = warning ? <LateEntryOtherExamNotice warning={warning} /> : null

    if (isTeacher && pending) {
      return (
        <div className="space-y-3">
          {notice}
          <p className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">
          {LATE_ENTRY_MESSAGES.pendingInline}{' '}
          <span className="sciem-tnum">{pending.hora_solicitud}</span>
          {' · '}{pending.ambiente.nro_aula}
          {' · '}{LATE_ENTRY_MESSAGES.requestedBy(pending.solicitante.nombre_completo)}
          </p>
        </div>
      )
    }
    if (isTeacher && result?.autorizado) {
      return (
        <div className="space-y-3">
          {notice}
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
        </div>
      )
    }

    return notice
  }

  const list = (
    <LateEntryRequestList
      title={isTeacher ? LATE_ENTRY_MESSAGES.listTitle : LATE_ENTRY_MESSAGES.listTitleOwn}
      requests={requests.requests}
      error={requests.error}
      throttle={requests.throttle}
      showRequester={isTeacher}
      actions={isTeacher && open && !timeOver ? {
        busyId: requests.busyId,
        onApprove: (request) => void requests.approve(request.id_solicitud, request.otro_examen !== null),
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

  if (timeOver) {
    return (
      <div className="space-y-5">
        <StatePanel tone="neutral" icon={Clock} title={LATE_ENTRY_MESSAGES.timeOverTitle}>
          {LATE_ENTRY_MESSAGES.timeOver}
        </StatePanel>
        <Button asChild className="h-11 w-full sm:w-auto">
          <Link to={LATE_ENTRY_EXAMS_PATH}>{LATE_ENTRY_MESSAGES.backToListShort}</Link>
        </Button>
        {list}
      </div>
    )
  }

  const actions = isTeacher
    ? (warning ? TEACHER_OTHER_EXAM_ACTIONS : TEACHER_LATE_ENTRY_ACTIONS)
    : (warning ? ASSISTANT_OTHER_EXAM_ACTIONS : ASSISTANT_LATE_ENTRY_ACTIONS)

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
            onSelect={verification.choose}
            onVerify={() => void verification.verify()}
            searchSource={LATE_ENTRY_SEARCH}
          />
        </div>
        <div className="min-w-0 space-y-5">
          {result ? (
            <VerificationResult
              result={result}
              presentedCi={verification.ci}
              subjectName={exam.materia}
              busy={busy !== null}
              actions={actions}
              caution={warning ? LATE_ENTRY_MESSAGES.cautionTitle : undefined}
              actionsLayout="card"
              onConfirm={confirm}
              onReject={next}
              onNext={next}
              extra={extra()}
              footer={footer()}
            />
          ) : list}
        </div>
      </div>
      <LateEntryOtherExamDialog
        open={confirmingOtherExam}
        studentName={result?.estudiante?.nombre_completo ?? ''}
        busy={busy !== null}
        onOpenChange={setConfirmingOtherExam}
        onConfirm={() => {
          setConfirmingOtherExam(false)
          register(true)
        }}
      />
    </div>
  )
}
