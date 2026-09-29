import { MoreVertical, Repeat2, Trash2, Flag } from 'lucide-react'
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
  onRemove: () => void
  onReport: () => void
  disabled?: boolean
}

/**
 * Menú de tres puntos por auxiliar.
 */
export function AssistantRowMenu({
  onMove,
  onRemove,
  onReport,
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

      <DropdownMenuContent align="end" className="w-56 z-50">
        <DropdownMenuItem onClick={onMove} className="gap-2">
          <Repeat2 className="size-4" />
          Mover de grupo
        </DropdownMenuItem>

        <DropdownMenuItem
          onClick={onRemove}
          className="gap-2 text-destructive focus:text-destructive"
        >
          <Trash2 className="size-4" />
          Quitar de un grupo
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem onClick={onReport} className="gap-2">
          <Flag className="size-4" />
          Reportar auxiliar
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}