import { Card } from '@/components/ui/card'
import { LoadingState } from '@/components/common/LoadingState'
import { ErrorState } from '@/components/common/ErrorState'
import { cn } from '@/lib/utils'
import type { useRoomDistribution } from '../hooks/useRoomDistribution'
import { ChangesBanner } from './ChangesBanner'
import { DistributionActionBar } from './DistributionActionBar'
import { DistributionFeedback } from './DistributionFeedback'
import { DistributionSummary } from './DistributionSummary'
import { RoomCard } from './RoomCard'
import { UnassignedStudentsCard } from './UnassignedStudentsCard'
import { DISTRIBUTION_MESSAGES } from '../utils/roomDistributionMessages'

interface Props {
  distribution: ReturnType<typeof useRoomDistribution>
  onPropose: () => void
  onDiscard: () => void
  onReload: () => void
}

export function DistributionWorkspace({ distribution: state, onPropose, onDiscard, onReload }: Props) {
  if (state.loading) return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      {[0, 1].map((index) => <Card key={index}><LoadingState rows={3} label="Cargando distribución" /></Card>)}
    </div>
  )
  if (state.loadError) return <Card><ErrorState error={state.loadError} onRetry={onReload} /></Card>
  const { data, summary } = state
  if (!data || !summary) return null
  const students = data.estudiantes.map((student) => ({
    ...student, id_ambiente: state.assignments.get(student.id_estudiante) ?? null,
  }))
  const editable = state.actions.canAct
  const busy = state.actions.operation !== null
  return (
    <>
      <DistributionSummary data={data} summary={summary} />
      <DistributionFeedback data={data} missingSeats={summary.missingSeats} unassigned={summary.unassigned}
        error={state.actions.error} mustReload={state.actions.mustReload} busy={busy} onReload={onReload} />
      <ChangesBanner count={summary.changes} isSaving={busy} onSave={() => { void state.actions.save() }}
        onDiscard={onDiscard} message={DISTRIBUTION_MESSAGES.unsaved} showActions={false} />
      <div className={cn('grid grid-cols-1 items-start gap-4 lg:grid-cols-2', summary.rooms.length >= 3 && 'xl:grid-cols-3')}>
        {summary.rooms.map((room) => <RoomCard key={room.id_ambiente} room={room} rooms={summary.rooms}
          students={students.filter((student) => student.id_ambiente === room.id_ambiente)}
          editable={editable} onMove={state.moveStudent} />)}
      </div>
      <UnassignedStudentsCard students={students.filter((student) => student.id_ambiente === null)}
        rooms={summary.rooms} editable={editable} onMove={state.moveStudent} />
      {data.editable && <DistributionActionBar busy={busy} proposing={state.actions.operation === 'proposal'}
        saving={state.actions.operation === 'save'} canPropose={editable && data.total_habilitados > 0}
        canSave={state.canSave} isDirty={state.isDirty} onPropose={onPropose}
        onDiscard={onDiscard} onSave={() => { void state.actions.save() }} />}
    </>
  )
}
