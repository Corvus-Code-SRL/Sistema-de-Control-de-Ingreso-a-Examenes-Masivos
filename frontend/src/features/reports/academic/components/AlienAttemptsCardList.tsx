import { ShieldAlert } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { initialsOf } from '@/features/risk-center/academic/utils/format'
import type { AlienAttemptsStudent } from '../types/alienAttempts.types'
import { examenesLabel } from './AlienAttemptsTable'

interface AlienAttemptsCardListProps {
  records: AlienAttemptsStudent[]
}

/** Reporte en móvil: una tarjeta por estudiante con los mismos datos que la tabla. */
export function AlienAttemptsCardList({ records }: AlienAttemptsCardListProps) {
  return (
    <ul aria-label="Estudiantes con intentos a exámenes ajenos" className="md:hidden">
      {records.map((record) => (
        <li key={record.id_estudiante} className="border-b border-border last:border-b-0">
          <div className="flex items-start gap-3 px-4 py-3">
            <Avatar className="size-9 shrink-0">
              <AvatarFallback
                className="bg-brand-soft text-xs font-semibold text-brand-deep"
                aria-hidden="true"
              >
                {initialsOf(record.nombre_completo)}
              </AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1 space-y-1">
              <p className="truncate text-sm font-medium text-foreground">{record.nombre_completo}</p>
              <p className="sciem-tnum text-xs text-muted-foreground">
                {record.cod_sis} · {record.intentos} {record.intentos === 1 ? 'intento' : 'intentos'}
              </p>
              <p className="line-clamp-2 text-xs text-muted-foreground">{examenesLabel(record.examenes)}</p>

              {record.id_cr_estudiante !== null && (
                <Link
                  to={`/central-riesgo/${record.id_cr_estudiante}`}
                  aria-label={`Ver registro en la Central de Riesgo de ${record.nombre_completo}`}
                  className="inline-flex"
                >
                  <Badge variant="destructive" className="gap-1">
                    <ShieldAlert className="size-3" aria-hidden="true" />
                    En CR
                  </Badge>
                </Link>
              )}
            </div>
          </div>
        </li>
      ))}
    </ul>
  )
}
