import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import type { ToastVariant } from '@/components/ui/toast'
import { roomDistributionService } from '../services/roomDistributionService'
import type { DistributionAssignments, DistributionProposal, RoomDistributionView } from '../types/roomDistribution.types'
import { distributionApiError, distributionSummary, proposalMatchesSnapshot } from '../utils/roomDistributionUtils'
import { DISTRIBUTION_MESSAGES as messages } from '../utils/roomDistributionMessages'

interface DistributionNotice { title: string; description: string; variant: ToastVariant }
interface ActionOptions {
  examId: number | null
  snapshot: RoomDistributionView | null
  data: RoomDistributionView | null
  assignments: DistributionAssignments
  loading: boolean
  commit: (data: RoomDistributionView) => void
  applyProposal: (proposal: DistributionProposal) => void
}
type Operation = 'proposal' | 'save' | 'stop'

export function useDistributionActions(options: ActionOptions) {
  const { examId, snapshot, data, assignments, loading, commit, applyProposal } = options
  const [operation, setOperation] = useState<Operation | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [notice, setNotice] = useState<DistributionNotice | null>(null)
  const active = useRef<AbortController | null>(null)
  const mustReload = error?.status === 409 || error?.status === 403 || error?.status === 404

  useEffect(() => {
    setOperation(null)
    setError(null)
    setNotice(null)
    return () => { active.current?.abort(); active.current = null }
  }, [examId, snapshot])

  const execute = useCallback(async <T,>(
    next: Operation,
    request: (signal: AbortSignal) => Promise<T>,
    success: (result: T) => void,
  ): Promise<boolean> => {
    if (active.current) return false
    const controller = new AbortController()
    active.current = controller
    setOperation(next)
    setError(null)
    try {
      const result = await request(controller.signal)
      if (controller.signal.aborted) return false
      success(result)
      return true
    } catch (cause: unknown) {
      if (!controller.signal.aborted) {
        const failure = distributionApiError(cause)
        setError(failure)
        setNotice({ title: 'No se pudo completar la operación', variant: 'error',
          description: failure.isValidation ? messages.validationTitle : failure.message })
      }
      return false
    } finally {
      if (active.current === controller) {
        active.current = null
        if (!controller.signal.aborted) setOperation(null)
      }
    }
  }, [])

  const canAct = Boolean(data?.editable && !loading && !mustReload && operation === null)
  const propose = useCallback(async (): Promise<boolean> => {
    if (!canAct || !data || examId === null || data.total_habilitados === 0) return false
    return execute('proposal', (signal) => roomDistributionService.propose(examId, signal), (proposal) => {
      if (!proposalMatchesSnapshot(data, proposal)) throw new ApiError(409, messages.changed)
      applyProposal(proposal)
      setNotice({ title: messages.proposalApplied, description: messages.proposalPending, variant: 'success' })
    })
  }, [canAct, data, examId, execute, applyProposal])

  const save = useCallback(async (): Promise<boolean> => {
    if (!canAct || !data || examId === null) return false
    const summary = distributionSummary(data, assignments)
    if (!summary.complete || (summary.changes === 0 && data.total_habilitados > 0)) return false
    const payload = {
      version: data.version,
      asignaciones: data.estudiantes.flatMap((student) => {
        const roomId = assignments.get(student.id_estudiante)
        return roomId === undefined || roomId === null ? []
          : [{ id_estudiante: student.id_estudiante, id_ambiente: roomId }]
      }),
    }
    return execute('save', (signal) => roomDistributionService.save(examId, payload, signal), (updated) => {
      commit(updated)
      setNotice({ title: messages.saved, description: messages.saveSuccess, variant: 'success' })
    })
  }, [canAct, data, examId, assignments, execute, commit])

  const canStop = Boolean(data?.examen.puede_dejar_de_usar && !loading && operation === null)
  const stop = useCallback(async (): Promise<boolean> => {
    if (!canStop || examId === null) return false
    return execute('stop', (signal) => roomDistributionService.stop(examId, signal), (updated) => {
      commit(updated)
      setNotice({ title: messages.stopped, description: messages.stoppedDescription, variant: 'success' })
    })
  }, [canStop, examId, execute, commit])

  const roomFull = useCallback((): void => {
    setNotice({ title: messages.roomFull, description: 'Seleccione otro ambiente con lugares disponibles.', variant: 'error' })
  }, [])
  const clearNotice = useCallback(() => setNotice(null), [])
  const clearValidation = useCallback(() => setError((previous) => previous?.isValidation ? null : previous), [])
  return { operation, error, notice, mustReload, canAct, canStop, propose, save, stop, roomFull, clearNotice, clearValidation }
}
