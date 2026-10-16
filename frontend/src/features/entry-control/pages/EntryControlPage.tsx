import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, RefreshCw } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { EntryExamCard } from '../components/EntryExamCard'
import { IdentificationForm } from '../components/IdentificationForm'
import { IncidentReportHandoffDialog } from '../components/IncidentReportHandoffDialog'
import { RecentEntries } from '../components/RecentEntries'
import { VerificationResult } from '../components/VerificationResult'
import { useEntryStatus } from '../hooks/useEntryStatus'
import { useStudentVerification } from '../hooks/useStudentVerification'

export function EntryControlPage() {
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)
  if (!Number.isInteger(id) || id <= 0) return <AppShell mobileTitle="Control de ingreso" breadcrumbs={[{ label: 'Control de ingreso' }]}><p role="alert">Examen no válido.</p></AppShell>
  return <EntryControlContent key={id} examId={id} />
}

function EntryControlContent({ examId }: { examId: number }) {
  const { context, status, openingFailure, loading, error, statusError, ended, refresh, reload } = useEntryStatus(examId)
  const [roomId, setRoomId] = useState<number | null>(null)
  const [incidentOpen, setIncidentOpen] = useState(false)
  const [incidentAttemptId, setIncidentAttemptId] = useState<number | null>(null)
  const verification = useStudentVerification(examId, roomId, () => { void refresh(true) })

  useEffect(() => {
    if (!context) return
    setRoomId(context.id_ambiente_asignado ?? context.ambientes[0]?.id_ambiente ?? null)
  }, [context])

  const open = context?.estado === 'EN_INGRESO' && !ended
  const hasRoom = roomId !== null && context?.ambientes.some((room) => room.id_ambiente === roomId)

  function reportIncidence(attemptId?: number) {
    if (verification.result?.estudiante && roomId) {
      setIncidentAttemptId(attemptId ?? verification.result.id_intento ?? null)
      setIncidentOpen(true)
    }
  }

  async function rejectAndReport() {
    const attemptId = await verification.reject('DECISION_CONTROLADOR', '')
    if (attemptId !== null) reportIncidence(attemptId)
  }

  return <AppShell mobileTitle="Control de ingreso" mobileSubtitle={context?.nombre_examen} breadcrumbs={[{ label: 'Control de ingreso', to: '/control-ingreso' }, { label: context?.nombre_examen ?? 'Examen' }]}>
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div><Link to="/control-ingreso" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-brand-deep"><ArrowLeft className="size-4" />Control de ingreso</Link><h1 className="sciem-h1 mt-1">Control de ingreso</h1></div>
      <Button type="button" variant="outline" size="sm" onClick={() => void reload()} disabled={!context}><RefreshCw className="size-4" />Actualizar</Button>
    </div>

    {loading && <div role="status" className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Cargando examen y estado del ingreso…</div>}
    {!loading && error && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-soft p-5"><p className="font-medium text-danger-fg">No se pudo abrir el control de ingreso</p><p className="mt-1 text-sm">{error}</p><Button variant="outline" className="mt-4" onClick={() => void reload()}>Reintentar</Button></div>}

    {!loading && context && <>
      <div className={verification.result && !ended ? 'hidden lg:block' : ''}><EntryExamCard context={context} status={status} stale={statusError !== null} roomId={roomId} roomDisabled={verification.busy !== null || ended} ended={ended} onRoomChange={(id) => { setRoomId(id); verification.reset() }} /></div>
      {!open && <div role="status" className="rounded-lg border border-warn-border bg-warn-soft p-4 text-sm text-warn-fg">{ended ? 'El control de ingreso ha terminado.' : openingFailure?.mensaje ?? `El control de ingreso no está abierto. Estado del examen: ${context.estado.replace(/_/g, ' ')}.`}</div>}
      {!hasRoom && !openingFailure && <div role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger-fg">No hay un ambiente de control asignado. Solicite al docente que asigne uno.</div>}
      {statusError && <div role="alert" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">{open ? 'No se pudo actualizar el contador' : 'No se pudo actualizar el estado del control'}: {statusError}</div>}
      {verification.notice && <div role="status" className="flex items-center justify-between gap-2 rounded-lg border border-ok/30 bg-ok-soft p-3 text-sm font-medium text-ok-fg"><span>{verification.notice}</span><button type="button" onClick={() => verification.setNotice(null)} aria-label="Cerrar aviso" className="px-2">×</button></div>}
      {verification.error && <div role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger-fg">{verification.error}</div>}
      {open && <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <div className={verification.result ? 'hidden lg:block' : ''}>
          <IdentificationForm examId={examId} sis={verification.sis} ci={verification.ci} disabled={!open || !hasRoom || verification.busy !== null} busy={verification.busy === 'verify'} onSisChange={verification.changeSis} onCiChange={verification.changeCi} onSelect={verification.choose} onVerify={() => void verification.verify()} />
        </div>
        <div className="space-y-5">
          {verification.result ? <VerificationResult result={verification.result} presentedCi={verification.ci} subjectName={context.materia} busy={verification.busy !== null} onConfirm={() => void verification.confirm()} onReject={() => void rejectAndReport()} onReport={() => reportIncidence()} onNext={verification.reset} /> : <RecentEntries entries={status?.ultimos_ingresos ?? []} />}
        </div>
      </div>}
      <IncidentReportHandoffDialog open={incidentOpen} studentName={verification.result?.estudiante?.nombre_completo ?? ''} examId={examId} studentId={verification.result?.estudiante?.id_estudiante ?? 0} roomId={roomId ?? 0} attemptId={incidentAttemptId} onClose={() => { setIncidentOpen(false); if (verification.result?.autorizado) verification.reset() }} />
    </>}
  </AppShell>
}
