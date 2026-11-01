import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { throttleNotice, throttleWaitSeconds } from '@/lib/throttle'
import { entryControlService, isFullStatus, isPendingStatus } from '../services/entryControlService'
import type { EntryContext, EntryOpeningFailure, EntryStatus } from '../types/entry-control.types'

const NORMAL_POLL_MS = 2000
const FIRST_RETRY_MS = 3000
const MAX_RETRY_MS = 30000

function message(error: unknown): string {
  if (error instanceof ApiError && error.status >= 500) {
    return 'El servidor no pudo responder. Intente nuevamente.'
  }
  return error instanceof Error ? error.message : 'No se pudo cargar el control de ingreso.'
}

export function useEntryStatus(examId: number) {
  const [context, setContext] = useState<EntryContext | null>(null)
  const [status, setStatus] = useState<EntryStatus | null>(null)
  const [openingFailure, setOpeningFailure] = useState<EntryOpeningFailure | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [statusError, setStatusError] = useState<string | null>(null)
  const [lastUpdatedAt, setLastUpdatedAt] = useState<number | null>(() => {
    try {
      const stored = Number(window.sessionStorage.getItem(`entry-control:${examId}:updated-at`))
      return stored > 0 ? stored : null
    } catch {
      return null
    }
  })
  const [pollDelay, setPollDelay] = useState(NORMAL_POLL_MS)
  const [ended, setEnded] = useState(false)
  const version = useRef<string | undefined>(undefined)
  const inFlight = useRef(false)
  const networkFailures = useRef(0)
  const endedRef = useRef(false)

  const refresh = useCallback(async (force = false) => {
    if (inFlight.current || endedRef.current) return
    inFlight.current = true
    try {
      const next = await entryControlService.status(examId, force ? undefined : version.current)
      if (isPendingStatus(next)) {
        version.current = undefined
        setStatus(null)
        setOpeningFailure(next.motivo_apertura)
      } else {
        version.current = next.version
        if (isFullStatus(next)) setStatus(next)
        setOpeningFailure(null)
      }
      networkFailures.current = 0
      setPollDelay(NORMAL_POLL_MS)
      setStatusError(null)
      const updatedAt = Date.now()
      setLastUpdatedAt(updatedAt)
      try {
        window.sessionStorage.setItem(`entry-control:${examId}:updated-at`, String(updatedAt))
      } catch {
        // El control sigue funcionando aunque el navegador impida guardar este dato.
      }
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 409) {
        endedRef.current = true
        setEnded(true)
        setStatusError(null)
        return
      }
      if (cause instanceof ApiError && cause.isTooManyRequests) {
        // El sondeo sigue vivo: solo espera lo que pide el servidor antes de volver a consultar.
        const seconds = throttleWaitSeconds(cause)
        networkFailures.current = 0
        setPollDelay(seconds * 1000)
        setStatusError(throttleNotice(seconds))
        setLastUpdatedAt((previous) => previous ?? Date.now())
        return
      }
      if (cause instanceof ApiError && cause.isNetworkFailure) {
        networkFailures.current += 1
        setPollDelay(Math.min(FIRST_RETRY_MS * 2 ** (networkFailures.current - 1), MAX_RETRY_MS))
      } else {
        networkFailures.current = 0
        setPollDelay(NORMAL_POLL_MS)
      }
      setStatusError(message(cause))
      setLastUpdatedAt((previous) => previous ?? Date.now())
    } finally {
      inFlight.current = false
    }
  }, [examId])

  const load = useCallback(async () => {
    endedRef.current = false
    networkFailures.current = 0
    setEnded(false)
    setPollDelay(NORMAL_POLL_MS)
    setLoading(true)
    setError(null)
    setStatusError(null)
    try {
      const nextContext = await entryControlService.context(examId)
      setContext(nextContext)
      if (nextContext.estado === 'EN_INGRESO' || nextContext.estado === 'PROGRAMADO') {
        await refresh(true)
      } else {
        version.current = undefined
        setStatus(null)
        setOpeningFailure(null)
      }
    } catch (cause) {
      setError(message(cause))
      setLastUpdatedAt((previous) => previous ?? Date.now())
    } finally {
      setLoading(false)
    }
  }, [examId, refresh])

  useEffect(() => {
    version.current = undefined
    setContext(null)
    setStatus(null)
    setOpeningFailure(null)
    void load()
  }, [load])

  useEffect(() => {
    if (context?.estado !== 'EN_INGRESO' || ended) return

    let active = true
    let timer: number
    const poll = async () => {
      if (!document.hidden) await refresh()
      if (active && !endedRef.current) timer = window.setTimeout(poll, pollDelay)
    }
    timer = window.setTimeout(poll, pollDelay)

    return () => { active = false; window.clearTimeout(timer) }
  }, [context?.estado, ended, pollDelay, refresh])

  useEffect(() => {
    if (context?.estado !== 'PROGRAMADO' || ended) return

    let active = true
    let checking = false
    const timer = window.setInterval(async () => {
      if (document.hidden || checking) return
      checking = true
      try {
        const nextContext = await entryControlService.context(examId)
        if (active) {
          if (nextContext.estado !== 'PROGRAMADO') setContext(nextContext)
          if (nextContext.estado === 'EN_INGRESO' || nextContext.estado === 'PROGRAMADO') {
            await refresh(true)
          }
        }
      } catch {
        // El siguiente ciclo vuelve a consultar; el botón Actualizar sigue disponible.
      } finally {
        checking = false
      }
    }, 15000)

    return () => { active = false; window.clearInterval(timer) }
  }, [context?.estado, ended, examId, refresh])

  return { context, status, openingFailure, loading, error, statusError, lastUpdatedAt, ended, refresh, reload: load }
}
