import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { roomDistributionService } from '../services/roomDistributionService'
import { distributionApiError, distributionSummary, parseDistributionExamId } from '../utils/roomDistributionUtils'
import { DISTRIBUTION_EXAM_PARAM } from '../utils/roomDistributionMessages'
import type { DistributionAssignments, RoomDistributionView } from '../types/roomDistribution.types'
import { useDistributionDraft } from './useDistributionDraft'
import { useDistributionActions } from './useDistributionActions'

const EMPTY_ASSIGNMENTS: DistributionAssignments = new Map()

export function useRoomDistribution() {
  const [params, setParams] = useSearchParams()
  const exams = useAsyncResource(roomDistributionService.getExams, [])
  const available = exams.data ?? []
  const requested = parseDistributionExamId(params.get(DISTRIBUTION_EXAM_PARAM))
  const selectedExamId = available.find((exam) => exam.id_examen === requested)?.id_examen
    ?? available[0]?.id_examen ?? null
  const [isFetching, setIsFetching] = useState(false)
  const load = useCallback(async (signal: AbortSignal): Promise<RoomDistributionView | null> => {
    setIsFetching(true)
    try {
      if (selectedExamId === null) return null
      return { ...await roomDistributionService.getDistribution(selectedExamId, signal) }
    } finally {
      if (!signal.aborted) setIsFetching(false)
    }
  }, [selectedExamId])
  const resource = useAsyncResource(load, [selectedExamId])
  const snapshot = resource.data?.examen.id_examen === selectedExamId ? resource.data : null
  const editor = useDistributionDraft(snapshot)
  const data = editor.draft?.baseline.examen.id_examen === selectedExamId ? editor.draft.baseline : null
  const assignments = editor.draft?.assignments ?? EMPTY_ASSIGNMENTS
  const loading = isFetching || (selectedExamId !== null && !data && resource.status !== 'error')
  const actions = useDistributionActions({
    examId: selectedExamId, snapshot, data, assignments, loading,
    commit: editor.commit, applyProposal: editor.applyProposal,
  })

  const selectExam = useCallback((id: number): void => {
    setParams((previous) => {
      const next = new URLSearchParams(previous)
      next.set(DISTRIBUTION_EXAM_PARAM, String(id))
      return next
    }, { replace: true })
  }, [setParams])

  useEffect(() => {
    if (selectedExamId !== null && requested !== selectedExamId) selectExam(selectedExamId)
  }, [requested, selectedExamId, selectExam])

  const reload = useCallback((): void => {
    setIsFetching(true)
    resource.reload()
  }, [resource.reload])

  const moveStudent = useCallback((id: number, roomId: number): void => {
    if (!actions.canAct) return
    const result = editor.moveStudent(id, roomId)
    if (result === 'full') actions.roomFull()
    else if (result === 'moved') actions.clearValidation()
  }, [actions.canAct, actions.roomFull, actions.clearValidation, editor.moveStudent])

  const summary = data ? distributionSummary(data, assignments) : null
  return {
    exams: { ...exams, error: exams.error ? distributionApiError(exams.error) : null },
    selectedExamId, selectExam, data, assignments, summary, loading,
    loadError: resource.error ? distributionApiError(resource.error) : null,
    reload, discard: editor.discard, moveStudent, actions,
    isDirty: (summary?.changes ?? 0) > 0,
    canSave: actions.canAct && Boolean(summary?.complete)
      && ((summary?.changes ?? 0) > 0 || data?.total_habilitados === 0),
  }
}
