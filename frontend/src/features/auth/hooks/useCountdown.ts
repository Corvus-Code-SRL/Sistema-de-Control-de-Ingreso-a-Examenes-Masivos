import { useEffect, useMemo, useState } from 'react'

/**
 * Cuenta regresiva en segundos. Con `seconds` en `null` no corre.
 *
 * Se calcula contra la hora de fin y no restando de a uno: si la pestaña se duerme, al volver el
 * número es el real, no el que quedó congelado. Desde el primer render con un valor nuevo ya devuelve
 * ese valor (no pasa por 0), para que quien lo observe no confunda «arrancando» con «terminó».
 */
export function useCountdown(seconds: number | null): number {
  const [now, setNow] = useState(() => Date.now())

  const endsAt = useMemo(
    () => (seconds === null ? null : Date.now() + seconds * 1000),
    [seconds]
  )

  useEffect(() => {
    if (endsAt === null) return

    setNow(Date.now())
    const timer = setInterval(() => setNow(Date.now()), 1000)

    return () => clearInterval(timer)
  }, [endsAt])

  if (seconds === null || endsAt === null) return 0

  // Con un `now` viejo (el primer render tras cambiar `seconds`) el resultado queda en `seconds`.
  return Math.min(seconds, Math.max(0, Math.ceil((endsAt - now) / 1000)))
}

/** `0:47` — minutos y segundos con dos dígitos de segundos. */
export function formatCountdown(totalSeconds: number): string {
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60

  return `${minutes}:${String(seconds).padStart(2, '0')}`
}
