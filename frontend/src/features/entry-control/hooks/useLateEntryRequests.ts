import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { throttleNotice, throttleWaitSeconds } from '@/lib/throttle'
import { LATE_ENTRY_POLL_MS } from '../constants'
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
 * Solicitudes de ingreso con retraso del examen: el docente ve todas y el auxiliar solo las suyas (lo
 * decide el servidor). Se cargan una vez por examen y, mientras `enabled` (examen EN_CURSO antes de su
 * fin programado), se consultan cada LATE_ENTRY_POLL_MS con la pestaña visible. Al cerrarse el examen
 * deja de consultar y conserva la última lista; si la vista se abre con el examen ya cerrado, muestra
 * la lista final.
 * `announcement` es el texto para la región aria-live cuando llega una solicitud o se resuelve una.
 * Ante un 429 (RNF-03) conserva la lista, avisa con `throttle` y se salta los ciclos dentro de la
 * espera que pide el servidor, igual que la vista En curso.
 */
export function useLateEntryRequests(examId: number, role: ControllerRole, enabled: boolean) {
  const [requests, setRequests] = useState<LateEntryRequest[] | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [announcement, setAnnouncement] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [throttle, setThrottle] = useState<string | null>(null)
  const previous = useRef<Statuses | null>(null)
  const pausedUntil = useRef(0)
  const loadedExamId = useRef<number | null>(null)

  const apply = useCallback((next: LateEntryRequest[]) => {
    if (previous.current !== null) {
      const message = transitionOf(previous.current, next, role)
      if (message) setAnnouncement(message)
    }
    previous.current = statusesOf(next)
    setRequests(next)
  }, [role])

  useEffect(() => {
    const controller = new AbortController()
    let inFlight = false

    async function load() {
      if (inFlight) return
      inFlight = true
      try {
        const next = await lateEntryService.list(examId, controller.signal)
        if (controller.signal.aborted) return
        apply(next)
        loadedExamId.current = examId
        setError(null)
        setThrottle(null)
      } catch (cause) {
        if (controller.signal.aborted) return
        if (cause instanceof ApiError && cause.isTooManyRequests) {
          const seconds = throttleWaitSeconds(cause)
          pausedUntil.current = Date.now() + seconds * 1000
          setThrottle(throttleNotice(seconds))
          return
        }
        setError(cause instanceof ApiError ? cause : new ApiError(0, LATE_ENTRY_MESSAGES.actionFailed))
      } finally {
        inFlight = false
      }
    }

    if (enabled || loadedExamId.current !== examId) void load()
    if (!enabled) return () => controller.abort()

    const timer = window.setInterval(() => {
      if (!document.hidden && Date.now() >= pausedUntil.current) void load()
    }, LATE_ENTRY_POLL_MS)

    return () => {
      controller.abort()
      window.clearInterval(timer)
    }
  }, [examId, enabled, apply])

  async function resolve(requestId: number, approve: boolean, confirmOtherExam = false): Promise<boolean> {
    setBusyId(requestId)
    setActionError(null)
    try {
      const updated = approve
        ? await lateEntryService.approve(examId, requestId, confirmOtherExam)
        : await lateEntryService.reject(examId, requestId)
      previous.current?.set(updated.id_solicitud, updated.estado)
      setRequests((current) => current?.map((item) => item.id_solicitud === updated.id_solicitud ? updated : item) ?? null)
      setAnnouncement(approve ? LATE_ENTRY_MESSAGES.approved : LATE_ENTRY_MESSAGES.rejected)
      return true
    } catch (cause) {
      if (cause instanceof ApiError && cause.isTooManyRequests) {
        setActionError(LATE_ENTRY_MESSAGES.throttled(throttleWaitSeconds(cause)))
        return false
      }
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
    throttle,
    announcement,
    busyId,
    actionError,
    /** `confirmOtherExam`: autoriza con la advertencia de otro examen a la vista. */
    approve: (requestId: number, confirmOtherExam = false) => resolve(requestId, true, confirmOtherExam),
    reject: (requestId: number) => resolve(requestId, false),
    track,
    clearAnnouncement: () => setAnnouncement(null),
  }
}
