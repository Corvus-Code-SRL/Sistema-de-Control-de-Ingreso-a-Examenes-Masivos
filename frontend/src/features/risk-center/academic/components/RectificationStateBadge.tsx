import { FilePenLine, ShieldCheck } from 'lucide-react'
import { formatDate } from '../utils/format'
import type { RectificationState } from '../types/riskRectification.types'

interface RectificationStateBadgeProps {
  state: RectificationState
  count: number
  /** ISO 8601 de la última rectificación. */
  lastAt: string | null
}

/** «Original» (nunca rectificado) o «Rectificado», con cuántas veces y cuándo fue la última. */
export function RectificationStateBadge({ state, count, lastAt }: RectificationStateBadgeProps) {
  const isRectified = state === 'Rectificado'
  const Icon = isRectified ? FilePenLine : ShieldCheck

  return (
    <div className="flex flex-col gap-0.5">
      <span
        className={
          isRectified
            ? 'inline-flex w-fit items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-xs font-medium text-info'
            : 'inline-flex w-fit items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
        }
      >
        <Icon className="size-3 shrink-0" aria-hidden="true" />
        {state}
      </span>

      {isRectified && lastAt !== null && (
        <span className="text-xs text-muted-foreground">
          {count === 1 ? '1 rectificación' : `${count} rectificaciones`} · última {formatDate(lastAt)}
        </span>
      )}
    </div>
  )
}
