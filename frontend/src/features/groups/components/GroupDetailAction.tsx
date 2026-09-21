import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { groupLabel, type Group } from '../types/group.types'

interface GroupDetailActionProps {
  group: Group
  compact?: boolean
}

/**
 * Acceso al detalle de un grupo.
 *
 * Solo el docente dueño puede abrirlo, así que sobre un grupo ajeno no se
 * dibuja la acción: se dice que es de solo lectura en lugar de ofrecer algo
 * que el servidor rechazaría.
 */
export function GroupDetailAction({ group, compact = false }: GroupDetailActionProps) {
  if (!group.es_mio) {
    return <span className="text-xs text-muted-foreground">Solo lectura</span>
  }

  const to = `/cursos/${group.id_grupo}`
  const label = `Ver detalles del ${groupLabel(group).toLowerCase()}`

  if (compact) {
    return (
      <Button variant="ghost" size="icon" asChild>
        <Link to={to} aria-label={label}>
          <ChevronRight className="size-4" />
        </Link>
      </Button>
    )
  }

  return (
    <Button variant="outline" size="sm" asChild>
      <Link to={to} aria-label={label}>
        Ver detalles
        <ChevronRight className="size-4" />
      </Link>
    </Button>
  )
}
