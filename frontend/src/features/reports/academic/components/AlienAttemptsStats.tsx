import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { AlienAttemptsStats as AlienAttemptsStatsData } from '../types/alienAttempts.types'

interface AlienAttemptsStatsProps {
  stats: AlienAttemptsStatsData | null
}

/** Tres totales del reporte: intentos, estudiantes y cuántos ya están en la CR. */
export function AlienAttemptsStats({ stats }: AlienAttemptsStatsProps) {
  const values = stats ?? { intentos: 0, estudiantes: 0, enCentralRiesgo: 0 }

  return (
    <div className="grid gap-4 sm:grid-cols-3">
      <Card className="p-4" data-testid="stat-intentos">
        <p className="text-xs font-medium text-muted-foreground">Intentos en el rango</p>
        <p className="sciem-tnum mt-1 text-2xl font-bold">{values.intentos}</p>
      </Card>
      <Card className="p-4" data-testid="stat-estudiantes">
        <p className="text-xs font-medium text-muted-foreground">Estudiantes</p>
        <p className="sciem-tnum mt-1 text-2xl font-bold">{values.estudiantes}</p>
      </Card>
      <Card className="p-4" data-testid="stat-cr">
        <p className="text-xs font-medium text-muted-foreground">Ya en la Central de Riesgo</p>
        <p className={cn('sciem-tnum mt-1 text-2xl font-bold', values.enCentralRiesgo > 0 && 'text-[color:var(--danger)]')}>
            {values.enCentralRiesgo}
        </p>
      </Card>
    </div>
  )
}
