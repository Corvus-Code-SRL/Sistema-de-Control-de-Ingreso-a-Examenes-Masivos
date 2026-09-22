import { Lock } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/common/EmptyState'

/**
 * Intento de abrir un grupo ajeno.
 *
 * Se llega aquí por una URL escrita a mano o por un enlace antiguo: la interfaz
 * no ofrece la acción sobre grupos de otros docentes. El mensaje dice de quién
 * depende el acceso, sin exponer nada del grupo.
 */
export function CourseAccessDenied() {
  return (
    <EmptyState
      icon={Lock}
      title="Este curso no es suyo"
      description="Solo el docente que dicta el grupo puede ver su nómina, sus auxiliares y sus exámenes. Si cree que debería tener acceso, consúltelo con la dirección de carrera."
      action={
        <Button variant="outline" size="sm" asChild>
          <Link to="/mis-cursos">Ir a mis cursos</Link>
        </Button>
      }
    />
  )
}
