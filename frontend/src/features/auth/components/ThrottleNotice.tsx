import { useEffect, useState } from 'react'
import { formatCountdown } from '../hooks/useCountdown'

/**
 * Cuenta regresiva de la espera por demasiados intentos (429), con los segundos de `Retry-After`.
 *
 * El número se ve cada segundo pero no se anuncia cada segundo: un lector de pantalla que lo leyera
 * sin parar volvería inusable la pantalla. Lo anuncia con `aria-live="polite"` como máximo cada 15 s.
 */
const ANNOUNCE_EVERY_SECONDS = 15

export function ThrottleNotice({ remaining }: { remaining: number }) {
  const [announced, setAnnounced] = useState(remaining)

  useEffect(() => {
    if (remaining % ANNOUNCE_EVERY_SECONDS === 0) {
      setAnnounced(remaining)
    }
  }, [remaining])

  return (
    <div className="flex flex-col items-center gap-1 rounded-lg border border-warn-border bg-warn-soft p-4 text-center text-warn-fg">
      <span className="text-xs" aria-hidden="true">
        Podrá reintentar en
      </span>
      <span className="text-[32px] leading-[38px] font-bold tabular-nums" aria-hidden="true">
        {formatCountdown(remaining)}
      </span>
      <span className="sr-only" aria-live="polite">
        Podrá reintentar en {formatCountdown(announced)}
      </span>
    </div>
  )
}
