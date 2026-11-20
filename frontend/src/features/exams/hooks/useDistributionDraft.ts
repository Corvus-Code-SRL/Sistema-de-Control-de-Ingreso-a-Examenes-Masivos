import { useCallback, useEffect, useRef, useState } from 'react'
import type { DistributionAssignments, DistributionProposal, RoomDistributionView } from '../types/roomDistribution.types'
import { distributionAssignments, distributionSummary } from '../utils/roomDistributionUtils'

interface DistributionDraft {
  baseline: RoomDistributionView
  assignments: DistributionAssignments
}

export function useDistributionDraft(snapshot: RoomDistributionView | null) {
  const [draft, setDraft] = useState<DistributionDraft | null>(null)
  const current = useRef(draft)
  const update = useCallback((next: DistributionDraft | null): void => {
    current.current = next
    setDraft(next)
  }, [])
  const commit = useCallback((data: RoomDistributionView): void => {
    update({ baseline: data, assignments: distributionAssignments(data.estudiantes) })
  }, [update])

  useEffect(() => {
    if (snapshot) commit(snapshot)
    else update(null)
  }, [snapshot, commit, update])

  const discard = useCallback((): void => {
    if (current.current) commit(current.current.baseline)
  }, [commit])

  const applyProposal = useCallback((proposal: DistributionProposal): void => {
    if (current.current) {
      update({ ...current.current, assignments: distributionAssignments(proposal.estudiantes) })
    }
  }, [update])

  const moveStudent = useCallback((studentId: number, roomId: number): 'moved' | 'full' | 'unchanged' => {
    const value = current.current
    if (!value || !value.baseline.estudiantes.some((student) => student.id_estudiante === studentId)) return 'unchanged'
    if (value.assignments.get(studentId) === roomId) return 'unchanged'
    const room = distributionSummary(value.baseline, value.assignments).rooms.find((item) => item.id_ambiente === roomId)
    if (!room) return 'unchanged'
    if (room.asignados >= room.capacidad) return 'full'
    const next = new Map(value.assignments)
    next.set(studentId, roomId)
    update({ ...value, assignments: next })
    return 'moved'
  }, [update])

  return { draft, commit, discard, applyProposal, moveStudent }
}
