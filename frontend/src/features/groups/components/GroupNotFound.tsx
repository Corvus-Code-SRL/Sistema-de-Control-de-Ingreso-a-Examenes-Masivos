import { SearchX } from 'lucide-react'
import { Link } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { Button } from '@/components/ui/button'
import { MY_COURSES_PATH } from '../utils/paths'

/** Grupo inexistente o dado de baja: lo comparten el detalle del grupo y su carga de nómina. */
export function GroupNotFound() {
  return (
    <EmptyState
      icon={SearchX}
      title="El grupo no existe"
      description="El grupo que intenta abrir no está registrado o fue dado de baja."
      action={
        <Button variant="outline" size="sm" asChild>
          <Link to={MY_COURSES_PATH}>Ir a mis grupos</Link>
        </Button>
      }
    />
  )
}
