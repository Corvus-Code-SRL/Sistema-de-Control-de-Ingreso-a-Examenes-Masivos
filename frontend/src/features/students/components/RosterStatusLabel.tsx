import {
  AlertTriangle,
  CircleCheck,
  HelpCircle,
  UserPlus,
  UserRoundPlus,
} from 'lucide-react'

import { Badge } from '@/components/ui/badge'

import type { RosterRowState } from '../types/roster.types'
import { rowStateText } from '../utils/rosterRows'

interface RosterStatusLabelProps {
  estado: RosterRowState
  className?: string
}

/**
 * Presenta el estado de una fila con icono y texto.
 *
 * La etiqueta visual se deriva del estado clasificado por el backend.
 */
export function RosterStatusLabel({
  estado,
  className,
}: RosterStatusLabelProps) {
  const stateText = rowStateText(estado)

  const Icon = getStateIcon(estado)

  const toneClassName = {
    ok: 'border-ok/20 bg-ok-soft text-ok-fg',
    neutral: 'border-border-soft bg-sunken text-muted-foreground',
    warn: 'border-warn-border bg-warn-soft text-warn-fg',
    danger: 'border-danger/20 bg-danger-soft text-danger-fg',
  }[stateText.tone]

  return (
    <Badge
      variant="outline"
      className={`gap-1.5 ${toneClassName} ${className ?? ''}`}
    >
      <Icon aria-hidden="true" />
      <span>{stateText.label}</span>
    </Badge>
  )
}

function getStateIcon(estado: RosterRowState) {
  switch (estado) {
    case 'new_student':
      return UserPlus

    case 'existing_student':
      return UserRoundPlus

    case 'already_enrolled':
      return CircleCheck

    case 'inconsistent':
      return AlertTriangle

    default:
      return HelpCircle
  }
}