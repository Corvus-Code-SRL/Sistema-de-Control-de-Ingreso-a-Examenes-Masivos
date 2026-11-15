import { CheckCircle2, History } from 'lucide-react'
import type { VersionState } from '../types/riskRectification.types'

interface VersionStateBadgeProps {
  state: VersionState
}

/** «Vigente» (la que rige hoy) o «Anterior» (reemplazada por una rectificación); icono y texto, no solo color. */
export function VersionStateBadge({ state }: VersionStateBadgeProps) {
  const isCurrent = state === 'Vigente'
  const Icon = isCurrent ? CheckCircle2 : History

  return (
    <span
      className={
        isCurrent
          ? 'inline-flex w-fit items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-xs font-medium text-info'
          : 'inline-flex w-fit items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
      }
    >
      <Icon className="size-3 shrink-0" aria-hidden="true" />
      {state}
    </span>
  )
}
