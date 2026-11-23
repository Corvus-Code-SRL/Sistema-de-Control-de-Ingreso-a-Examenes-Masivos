import { CalendarX2 } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Card } from '@/components/ui/card'
import { Toast } from '@/components/ui/toast'
import { useRoomDistribution } from '../hooks/useRoomDistribution'
import { useDistributionLeaveGuard } from '../hooks/useDistributionLeaveGuard'
import { DistributionConfirmDialog } from '../components/DistributionConfirmDialog'
import { DistributionExamSelector } from '../components/DistributionExamSelector'
import { DistributionWorkspace } from '../components/DistributionWorkspace'
import { DISTRIBUTION_MESSAGES as messages } from '../utils/roomDistributionMessages'

export function RoomDistributionPage() {
  const state = useRoomDistribution()
  const busy = state.actions.operation !== null
  const leave = useDistributionLeaveGuard(state.isDirty, busy)
  return (
    <AppShell mobileTitle={messages.title} breadcrumbs={[
      { label: 'Preparación de exámenes' }, { label: messages.title },
    ]}>
      <div className="space-y-5 pb-36 sm:pb-0">
        <PageHeader title={messages.title} subtitle={messages.subtitle} />
        {state.exams.status === 'loading' && <Card><LoadingState rows={3} label="Cargando exámenes Programados" /></Card>}
        {state.exams.error && <Card><ErrorState error={state.exams.error} onRetry={state.exams.reload} /></Card>}
        {state.exams.status === 'success' && state.exams.data?.length === 0 && (
          <Card><EmptyState icon={CalendarX2} title="No tiene exámenes Programados"
            description="Los exámenes que cree o en los que colabore aparecerán aquí para consultar su distribución." /></Card>
        )}
        {state.exams.status === 'success' && (state.exams.data?.length ?? 0) > 0 && (
          <>
            <DistributionExamSelector exams={state.exams.data ?? []} selectedId={state.selectedExamId} disabled={busy}
              onSelect={(id) => { if (id !== state.selectedExamId) leave.request('leave', () => state.selectExam(id)) }} />
            <DistributionWorkspace distribution={state}
              onPropose={() => leave.request('overwrite', () => { void state.actions.propose() })}
              onDiscard={() => leave.request('discard', state.discard)}
              onReload={() => leave.request('reload', state.reload)}
              onStop={() => leave.request('stop', () => { void state.actions.stop() }, true)} />
          </>
        )}
      </div>
      <DistributionConfirmDialog kind={leave.pending} busy={busy} onCancel={leave.cancel} onConfirm={leave.confirm} />
      {state.actions.notice && <Toast {...state.actions.notice} onClose={state.actions.clearNotice} />}
    </AppShell>
  )
}
