import { Badge } from '@/components/ui/badge'

interface ExceptionalBadgeProps {
  /** Motivo con el que se registró (HU-023); se ve al pasar el cursor y lo lee el lector de pantalla. */
  reason?: string | null
}

/** Marca «Excepcional» de un estudiante que no figuraba en la nómina del grupo. */
export function ExceptionalBadge({ reason }: ExceptionalBadgeProps) {
  return (
    <Badge variant="outline" title={reason ?? undefined} className="ml-2 align-middle">
      Excepcional
      {reason && <span className="sr-only">: {reason}</span>}
    </Badge>
  )
}
