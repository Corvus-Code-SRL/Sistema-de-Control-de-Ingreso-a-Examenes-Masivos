import type { ReactNode } from 'react'
import { ArrowLeft } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'

interface PageHeaderProps {
  title: string
  subtitle?: ReactNode
  /** Ruta de vuelta; dibuja la flecha a la izquierda del título. */
  backTo?: string
  backLabel?: string
  actions?: ReactNode
}

/** Encabezado de página: título, contexto académico y acciones principales. */
export function PageHeader({
  title,
  subtitle,
  backTo,
  backLabel = 'Volver',
  actions,
}: PageHeaderProps) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="flex min-w-0 items-start gap-2">
        {backTo && (
          <Button variant="ghost" size="icon" className="mt-0.5 shrink-0" asChild>
            <Link to={backTo} aria-label={backLabel}>
              <ArrowLeft className="size-4" />
            </Link>
          </Button>
        )}

        <div className="min-w-0 space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
          {subtitle && <div className="text-sm text-muted-foreground">{subtitle}</div>}
        </div>
      </div>

      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}
