import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { entryControlService, isFullStatus } from '../services/entryControlService'
import type { EntryContext, EntryStatus } from '../types/entry-control.types'

function message(error: unknown): string {
  if (error instanceof ApiError && error.status >= 500) {
    return 'El servidor no pudo responder. Intente nuevamente.'
  }
  return error instanceof Error ? error.message : 'No se pudo cargar el control de ingreso.'
}

export function useEntryStatus(examId: number) {
  const [context, setContext] = useState<EntryContext | null>(null)
  const [status, setStatus] = useState<EntryStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [statusError, setStatusError] = useState<string | null>(null)
  const version = useRef<string | undefined>(undefined)
  const inFlight = useRef(false)

  const refresh = useCallback(async (force = false) => {
    if (inFlight.current) return
    inFlight.current = true
    try {
      const next = await entryControlService.status(examId, force ? undefined : version.current)
      version.current = next.version
      if (isFullStatus(next)) setStatus(next)
      setStatusError(null)
    } catch (cause) {
      setStatusError(message(cause))
    } finally {
      inFlight.current = false
    }
  }, [examId])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    setStatusError(null)
    try {
      const nextContext = await entryControlService.context(examId)
      setContext(nextContext)
      if (nextContext.estado === 'EN_INGRESO') {
        await refresh(true)
      } else {
        version.current = undefined
        setStatus(null)
      }
    } catch (cause) {
      setError(message(cause))
    } finally {
      setLoading(false)
    }
  }, [examId, refresh])

  useEffect(() => {
    version.current = undefined
    setContext(null)
    setStatus(null)
    void load()
  }, [load])

  useEffect(() => {
    if (!context || context.estado !== 'EN_INGRESO') return
    const timer = window.setInterval(() => {
      if (!document.hidden) void refresh()
    }, 2000)
    return () => window.clearInterval(timer)
  }, [context, refresh])

  useEffect(() => {
    if (context?.estado !== 'PROGRAMADO') return

    let active = true
    let checking = false
    const timer = window.setInterval(async () => {
      if (document.hidden || checking) return
      checking = true
      try {
        const nextContext = await entryControlService.context(examId)
        if (active && nextContext.estado !== 'PROGRAMADO') {
          setContext(nextContext)
          if (nextContext.estado === 'EN_INGRESO') await refresh(true)
        }
      } catch {
        // El siguiente ciclo vuelve a consultar; el botón Actualizar sigue disponible.
      } finally {
        checking = false
      }
    }, 15000)

    return () => { active = false; window.clearInterval(timer) }
  }, [context?.estado, examId, refresh])

  return { context, status, loading, error, statusError, refresh, reload: load }
}
