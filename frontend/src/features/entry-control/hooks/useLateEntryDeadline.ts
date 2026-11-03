import { useEffect, useState } from 'react'
import { MAX_TIMEOUT_MS } from '../constants'

function remainingMs(endsAt: string, serverNow: string): number {
  return Date.parse(endsAt) - Date.parse(serverNow)
}

/**
 * true cuando llega el fin programado del examen. Cuenta con la hora del servidor de la última
 * carga, no con el reloj del equipo; cada recarga del examen vuelve a sincronizarlo.
 */
export function useLateEntryDeadline(endsAt: string, serverNow: string): boolean {
  const [ended, setEnded] = useState(() => remainingMs(endsAt, serverNow) <= 0)

  useEffect(() => {
    const remaining = remainingMs(endsAt, serverNow)
    if (Number.isNaN(remaining)) return undefined
    setEnded(remaining <= 0)
    if (remaining <= 0 || remaining > MAX_TIMEOUT_MS) return undefined

    const timer = window.setTimeout(() => setEnded(true), remaining)
    return () => window.clearTimeout(timer)
  }, [endsAt, serverNow])

  return ended
}
