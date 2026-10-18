import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, RefreshCw } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { EntryExamCard } from '../components/EntryExamCard'
import { IdentificationForm } from '../components/IdentificationForm'
import { ReportIncidentDialog, type ReportIncidentContext } from '@/features/incidents'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { RecentRejectedAttempts } from '../components/RecentRejectedAttempts'
import { attemptTime } from '../components/entryAttemptPresentation'
import type { CurrentRejectedAttempt } from '../types/entry-control.types'
import { RecentEntries } from '../components/RecentEntries'
import { VerificationResult } from '../components/VerificationResult'
import { useEntryStatus } from '../hooks/useEntryStatus'
import { useStudentVerification } from '../hooks/useStudentVerification'

const CAMPUS_TIME_ZONE = 'America/La_Paz'

function timeLabel(value: number): string {
  return new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: CAMPUS_TIME_ZONE,
  }).format(new Date(value))
}

export function EntryControlPage() {
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)
  if (!Number.isInteger(id) || id <= 0) return <AppShell mobileTitle="Control de ingreso" breadcrumbs={[{ label: 'Control de ingreso' }]}><p role="alert">Examen no válido.</p></AppShell>
  return <EntryControlContent key={id} examId={id} />
}

function EntryControlContent({ examId }: { examId: number }) {
  const { context, status, openingFailure, loading, error, statusError, lastUpdatedAt, ended, refresh, reload } = useEntryStatus(examId)
  const [roomId, setRoomId] = useState<number | null>(null)
  const [activityTab, setActivityTab] = useState('entries')
  const [rejectionCount, setRejectionCount] = useState<number | null>(null)
  const [reportContext, setReportContext] = useState<ReportIncidentContext | null>(null)
  const [rejectionsVersion, setRejectionsVersion] = useState(0)
  const verification = useStudentVerification(examId, roomId, () => {
    void refresh(true)
    setRejectionsVersion((value) => value + 1)
  })

  useEffect(() => {
    if (!context) return
    setRoomId(context.id_ambiente_asignado ?? context.ambientes[0]?.id_ambiente ?? null)
  }, [context])

  const open = context?.estado === 'EN_INGRESO' && !ended
  const hasRoom = roomId !== null && context?.ambientes.some((room) => room.id_ambiente === roomId)

  useEffect(() => { setReportContext(null); setRejectionCount(null) }, [roomId])

  function openReport(attempt: CurrentRejectedAttempt | null) {
    const room = context?.ambientes.find((item) => item.id_ambiente === attempt?.roomId)
    if (!open || !attempt || attempt.reported || !context || !room) return
    setReportContext({
      examId, examName: context.nombre_examen, roomId: attempt.roomId, attemptId: attempt.attemptId,
      student: attempt.student, roomName: room.nro_aula, reason: attempt.reason,
      timeLabel: attemptTime(attempt.registeredAt),
    })
  }

  async function rejectAndReport() {
    const attempt = await verification.reject('DECISION_CONTROLADOR', '')
    openReport(attempt)
  }

  function reported(alreadyReported: boolean) {
    if (reportContext) verification.markReported(reportContext.attemptId)
    verification.setNotice(alreadyReported ? 'Este intento ya fue reportado.' : 'Enviado a revisión del docente')
    setReportContext(null)
    setActivityTab('rejections')
    setRejectionsVersion((value) => value + 1)
  }

  return <AppShell mobileTitle="Control de ingreso" mobileSubtitle={context?.nombre_examen} breadcrumbs={[{ label: 'Control de ingreso', to: '/control-ingreso' }, { label: context?.nombre_examen ?? 'Examen' }]}>
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div><Link to="/control-ingreso" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-brand-deep"><ArrowLeft className="size-4" />Control de ingreso</Link><h1 className="sciem-h1 mt-1">Control de ingreso</h1></div>
      <Button type="button" variant="outline" size="sm" onClick={() => { setRejectionsVersion((value) => value + 1); void reload() }} disabled={!context}><RefreshCw className="size-4" />Actualizar</Button>
    </div>

    {loading && <div role="status" className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Cargando examen y estado del ingreso…</div>}
    {!loading && error && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-soft p-5"><p className="font-medium text-danger-fg">Datos sin actualizar desde {timeLabel(lastUpdatedAt ?? Date.now())}</p><p className="mt-1 text-sm">No se pudo abrir el control de ingreso. {error}</p><Button variant="outline" className="mt-4" onClick={() => { setRejectionsVersion((value) => value + 1); void reload() }}>Reintentar</Button></div>}

    {!loading && context && <>
      <div className={verification.result && !ended ? 'hidden lg:block' : ''}><EntryExamCard context={context} status={status} stale={statusError !== null} roomId={roomId} roomDisabled={verification.busy !== null || ended || reportContext !== null} ended={ended} onRoomChange={(id) => { setRoomId(id); verification.reset() }} /></div>
      {!open && <div role="status" className="rounded-lg border border-warn-border bg-warn-soft p-4 text-sm text-warn-fg">{ended ? 'El control de ingreso ha terminado.' : openingFailure?.mensaje ?? `El control de ingreso no está abierto. Estado del examen: ${context.estado.replace(/_/g, ' ')}.`}</div>}
      {!hasRoom && !openingFailure && <div role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger-fg">No hay un ambiente de control asignado. Solicite al docente que asigne uno.</div>}
      {statusError && <div role="alert" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">Datos sin actualizar desde {timeLabel(lastUpdatedAt ?? Date.now())}. {statusError}</div>}
      {verification.notice && <div role="status" className="flex items-center justify-between gap-2 rounded-lg border border-ok/30 bg-ok-soft p-3 text-sm font-medium text-ok-fg"><span>{verification.notice}</span><button type="button" onClick={() => verification.setNotice(null)} aria-label="Cerrar aviso" className="px-2">×</button></div>}
      {verification.error && <div role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger-fg">{verification.error}</div>}
      {open && <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <div className={verification.result ? 'hidden lg:block' : ''}>
          <IdentificationForm examId={examId} sis={verification.sis} ci={verification.ci} disabled={!open || !hasRoom || verification.busy !== null} busy={verification.busy === 'verify'} onSisChange={verification.changeSis} onCiChange={verification.changeCi} onSelect={verification.choose} onVerify={() => void verification.verify()} />
        </div>
        <div className="space-y-5">
          {verification.result ? <VerificationResult
            result={verification.result} presentedCi={verification.ci} subjectName={context.materia}
            busy={verification.busy !== null} onConfirm={() => void verification.confirm()}
            onReject={() => void rejectAndReport()}
            onReport={verification.rejectedAttempt ? () => openReport(verification.rejectedAttempt) : undefined}
            reported={verification.rejectedAttempt?.reported}
            onNext={() => { if (verification.rejectedAttempt) setActivityTab('rejections'); verification.reset() }}
          /> : <Tabs value={activityTab} onValueChange={setActivityTab}>
            <TabsList aria-label="Actividad del control de ingreso">
              <TabsTrigger value="entries">Ingresos</TabsTrigger>
              <TabsTrigger value="rejections">Rechazos{rejectionCount !== null ? ` · ${rejectionCount}` : ''}</TabsTrigger>
            </TabsList>
            <TabsContent value="entries"><RecentEntries entries={status?.ultimos_ingresos ?? []} /></TabsContent>
            <TabsContent value="rejections">
              {hasRoom && roomId !== null && <RecentRejectedAttempts
                key={roomId} examId={examId} examName={context.nombre_examen} roomId={roomId}
                refreshVersion={rejectionsVersion} onReport={setReportContext} onCount={setRejectionCount}
              />}
            </TabsContent>
          </Tabs>}
        </div>
      </div>}
      {open && reportContext && <ReportIncidentDialog
        key={reportContext.attemptId} context={reportContext}
        onClose={() => setReportContext(null)} onReported={reported}
      />}
    </>}
  </AppShell>
}
