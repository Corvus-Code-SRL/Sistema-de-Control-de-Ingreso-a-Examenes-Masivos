import { CheckCircle2, CircleSlash } from 'lucide-react'

interface RecordStatusBadgeProps {
  /** Estado tal como lo envía el backend: `ACTIVO` o `INACTIVO`. */
  status: string
}

/** Estado de una materia o de un par: icono y texto, el color nunca es lo único que lo distingue. */
export function RecordStatusBadge({ status }: RecordStatusBadgeProps) {
  if (status === 'ACTIVO') {
    return (
      <span className="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-medium text-ok-fg">
        <CheckCircle2 className="size-3" aria-hidden="true" />
        Activo
      </span>
    )
  }

  return (
    <span className="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
      <CircleSlash className="size-3" aria-hidden="true" />
      Inactivo
    </span>
  )
}
