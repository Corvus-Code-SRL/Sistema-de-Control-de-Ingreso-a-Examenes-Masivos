import {
  MoreVertical,
  Repeat2,
  Trash2,
  CalendarPlus,
  CalendarMinus,
} from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'

interface AssistantRowMenuProps {
  onMove: () => void
  onEnableForExam: () => void
  onRemoveFromExam: () => void
  onRemove: () => void
  /** True si el auxiliar está habilitado en al menos un examen PROGRAMADO. */
  hasExams: boolean
  disabled?: boolean
}

/**
 * Menú de tres puntos por auxiliar.
 */
export function AssistantRowMenu({
  onMove,
  onEnableForExam,
  onRemoveFromExam,
  onRemove,
  hasExams,
  disabled = false,
}: AssistantRowMenuProps) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          disabled={disabled}
          aria-label="Más acciones"
        >
          <MoreVertical className="size-4" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-60 z-50">
        <DropdownMenuItem onClick={onMove} className="gap-2 cursor-pointer">
          <Repeat2 className="size-4" />
          Mover de grupo
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem onClick={onEnableForExam} className="gap-2 cursor-pointer">
          <CalendarPlus className="size-4" />
          Habilitar para examen
        </DropdownMenuItem>

        {hasExams && (
          <DropdownMenuItem
            onClick={onRemoveFromExam}
            className="gap-2 cursor-pointer"
          >
            <CalendarMinus className="size-4" />
            Quitar de un examen
          </DropdownMenuItem>
        )}

        <DropdownMenuSeparator />

        <DropdownMenuItem
          onClick={onRemove}
          className="gap-2 cursor-pointer text-destructive focus:text-destructive"
        >
          <Trash2 className="size-4" />
          Quitar de un grupo
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}