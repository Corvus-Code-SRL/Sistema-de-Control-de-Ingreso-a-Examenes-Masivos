import { Ban, CalendarClock, CheckCircle2, DoorOpen, PlaySquare } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { ExamStatus } from '../types/exams.types'

const STATUS_STYLES: Record<ExamStatus, { label: string; icon: LucideIcon; className: string }> = {
  PROGRAMADO: { label: 'Programado', icon: CalendarClock, className: 'bg-ok-soft text-ok-fg' },
  EN_INGRESO: { label: 'En ingreso', icon: DoorOpen, className: 'bg-info-soft text-info' },
  EN_CURSO: { label: 'En curso', icon: PlaySquare, className: 'bg-info-soft text-info' },
  FINALIZADO: { label: 'Finalizado', icon: CheckCircle2, className: 'bg-muted text-muted-foreground' },
  CANCELADO: { label: 'Cancelado', icon: Ban, className: 'bg-danger-soft text-danger-fg' },
}

interface ExamStatusBadgeProps {
  status: ExamStatus
}

/** Estado del examen: siempre con icono y texto, el color nunca es lo único que lo distingue. */
export function ExamStatusBadge({ status }: ExamStatusBadgeProps) {
  const { label, icon: Icon, className } = STATUS_STYLES[status]

  return (
    <span className={`inline-flex w-fit shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>
      <Icon className="size-3" aria-hidden="true" />
      {label}
    </span>
  )
}
