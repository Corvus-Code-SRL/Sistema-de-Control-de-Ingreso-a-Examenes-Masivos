import { Badge } from '@/components/ui/badge'

interface ExceptionalMarkProps {
  /** Motivo con el que el docente registró al estudiante en este examen. */
  reason?: string | null
}

/** Marca «Excepcional» de un estudiante agregado solo a la lista de un examen (HU-023), con su motivo. */
export function ExceptionalMark({ reason }: ExceptionalMarkProps) {
  return (
    <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
      <Badge variant="outline" className="border-warn-border bg-warn-soft text-warn-fg">
        Excepcional
      </Badge>
      {reason && <span className="text-xs text-muted-foreground">Motivo: {reason}</span>}
    </span>
  )
}
