import { X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

interface SubjectFilterChipProps {
  subjectName: string
}

/**
 * Materia fijada como contexto de la vista de grupos (artboards 1.4 y 1.5).
 *
 * El filtro vive en la URL, así que quitarlo es volver a la selección de
 * materias: no queda estado que limpiar. Se muestra en todos los tamaños.
 */
export function SubjectFilterChip({ subjectName }: SubjectFilterChipProps) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <Badge variant="secondary" className="h-7 gap-1 pr-0.5 pl-2.5 text-sm font-semibold">
        Materia: {subjectName}
        <Button
          variant="ghost"
          size="icon-xs"
          className="rounded-full text-secondary-foreground hover:bg-brand-deep/10 hover:text-secondary-foreground"
          asChild
        >
          <Link to="/materias" aria-label={`Quitar el filtro de materia ${subjectName}`}>
            <X aria-hidden="true" />
          </Link>
        </Button>
      </Badge>

      <span className="hidden text-sm text-muted-foreground md:inline">Filtro activo</span>
    </div>
  )
}
