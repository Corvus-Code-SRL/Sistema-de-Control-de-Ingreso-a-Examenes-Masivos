import { ShieldAlert } from 'lucide-react'

interface FaultTypeBadgeProps {
  name: string
}

/** Tipo de falta de un registro: icono, texto y color; el color nunca es lo único que lo distingue. */
export function FaultTypeBadge({ name }: FaultTypeBadgeProps) {
  return (
    <span className="inline-flex w-fit max-w-full items-center gap-1 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger-fg">
      <ShieldAlert className="size-3 shrink-0" aria-hidden="true" />
      <span className="truncate">{name}</span>
    </span>
  )
}
