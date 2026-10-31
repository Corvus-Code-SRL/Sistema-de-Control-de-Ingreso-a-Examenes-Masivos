import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { ENTRY_CONTROL_POLL_MS } from '../constants'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { lateEntryService } from '../services/lateEntryService'
import {
  CONTROLLER_ROLE, LATE_ENTRY_REQUEST_STATUS, type ControllerRole, type LateEntryRequest, type LateEntryRequestStatus,
} from '../types/late-entry.types'

type Statuses = Map<number, LateEntryRequestStatus>

function statusesOf(requests: LateEntryRequest[]): Statuses {
  return new Map(requests.map((request) => [request.id_solicitud, request.estado]))
}

/** Lo que cambió desde la consulta anterior y merece un aviso para el rol. */
function transitionOf(previous: Statuses, requests: LateEntryRequest[], role: ControllerRole): string | null {
  if (role === CONTROLLER_ROLE.DOCENTE) {
    const fresh = requests.find((request) =>
      request.estado === LATE_ENTRY_REQUEST_STATUS.PENDIENTE && !previous.has(request.id_solicitud))

    return fresh ? LATE_ENTRY_MESSAGES.newRequest(fresh.estudiante.nombre_completo, fresh.ambiente.nro_aula) : null
  }

  const resolved = requests.find((request) =>
    previous.get(request.id_solicitud) === LATE_ENTRY_REQUEST_STATUS.PENDIENTE
    && request.estado !== LATE_ENTRY_REQUEST_STATUS.PENDIENTE)
  if (!resolved) return null

  return resolved.estado === LATE_ENTRY_REQUEST_STATUS.AUTORIZADA
    ? LATE_ENTRY_MESSAGES.waitingApproved
    : LATE_ENTRY_MESSAGES.waitingRejected
}

/**
 * Solicitudes de ingreso con retraso del examen, consultadas cada ENTRY_CONTROL_POLL_MS mientras la
 * pestaña está visible: el docente ve todas y el auxiliar solo las suyas (lo decide el servidor).
 * `announcement` es el texto para la región aria-live cuando llega una solicitud o se resuelve una.
 */
export function useLateEntryRequests(examId: number, role: ControllerRole, enabled: boolean) {
  const [requests, setRequests] = useState<LateEntryRequest[] | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [announcement, setAnnouncement] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const previous = useRef<Statuses | null>(null)

  const apply = useCallback((next: LateEntryRequest[]) => {
    if (previous.current !== null) {
      const message = transitionOf(previous.current, next, role)
      if (message) setAnnouncement(message)
    }
    previous.current = statusesOf(next)
    setRequests(next)
  }, [role])

  useEffect(() => {
    if (!enabled) return
    const controller = new AbortController()
    let inFlight = false

    async function load() {
      if (inFlight) return
      inFlight = true
      try {
        const next = await lateEntryService.list(examId, controller.signal)
        if (controller.signal.aborted) return
        apply(next)
        setError(null)
      } catch (cause) {
        if (controller.signal.aborted) return
        setError(cause instanceof ApiError ? cause : new ApiError(0, LATE_ENTRY_MESSAGES.actionFailed))
      } finally {
        inFlight = false
      }
    }

    void load()
    const timer = window.setInterval(() => {
      if (!document.hidden) void load()
    }, ENTRY_CONTROL_POLL_MS)

    return () => {
      controller.abort()
      window.clearInterval(timer)
    }
  }, [examId, enabled, apply])

  async function resolve(requestId: number, approve: boolean): Promise<boolean> {
    setBusyId(requestId)
    setActionError(null)
    try {
      const updated = approve
        ? await lateEntryService.approve(examId, requestId)
        : await lateEntryService.reject(examId, requestId)
      previous.current?.set(updated.id_solicitud, updated.estado)
      setRequests((current) => current?.map((item) => item.id_solicitud === updated.id_solicitud ? updated : item) ?? null)
      setAnnouncement(approve ? LATE_ENTRY_MESSAGES.approved : LATE_ENTRY_MESSAGES.rejected)
      return true
    } catch (cause) {
      setActionError(cause instanceof Error && cause.message !== '' ? cause.message : LATE_ENTRY_MESSAGES.actionFailed)
      return false
    } finally {
      setBusyId(null)
    }
  }

  /** Suma la solicitud recién enviada, así su resolución se anuncia aunque llegue antes de la siguiente consulta. */
  const track = useCallback((request: LateEntryRequest) => {
    previous.current = previous.current ?? new Map()
    previous.current.set(request.id_solicitud, request.estado)
    setRequests((current) => [
      request,
      ...(current ?? []).filter((item) => item.id_solicitud !== request.id_solicitud),
    ])
  }, [])

  return {
    requests,
    pending: (requests ?? []).filter((request) => request.estado === LATE_ENTRY_REQUEST_STATUS.PENDIENTE),
    error,
    announcement,
    busyId,
    actionError,
    approve: (requestId: number) => resolve(requestId, true),
    reject: (requestId: number) => resolve(requestId, false),
    track,
    clearAnnouncement: () => setAnnouncement(null),
  }
}
