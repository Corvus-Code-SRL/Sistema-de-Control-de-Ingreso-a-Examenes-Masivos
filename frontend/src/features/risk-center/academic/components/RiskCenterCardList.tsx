import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import type { RiskCenterRecordSummary } from '../types/riskCenter.types'
import { formatDate, initialsOf } from '../utils/format'
import { FaultTypeBadge } from './FaultTypeBadge'
import { examLabel } from './RiskCenterTable'
import { useDetailLink } from '../hooks/useDetailLink'

interface RiskCenterCardListProps {
  records: RiskCenterRecordSummary[]
}

/** Registros de la Central de Riesgo en móvil: una tarjeta por registro que abre el detalle. */
export function RiskCenterCardList({ records }: RiskCenterCardListProps) {
  const detailLink = useDetailLink()

  return (
    <ul aria-label="Registros" className="md:hidden">
      {records.map((record) => (
        <li key={record.id_cr_estudiante} className="border-b border-border last:border-b-0">
          <Link
            to={detailLink(record.id_cr_estudiante)}
            className="flex items-center gap-3 px-4 py-3 hover:bg-sunken focus-visible:bg-sunken"
          >
            <Avatar className="size-9 shrink-0">
              <AvatarFallback className="bg-brand-soft text-xs font-semibold text-brand-deep" aria-hidden="true">
                {initialsOf(record.estudiante.nombre_completo)}
              </AvatarFallback>
            </Avatar>

            <span className="min-w-0 flex-1 space-y-1">
              <span className="block truncate text-sm font-medium text-foreground">
                {record.estudiante.nombre_completo}
              </span>
              <span className="sciem-tnum block text-xs text-muted-foreground">
                {record.estudiante.cod_sis} · {formatDate(record.fecha_registro)}
              </span>
              <FaultTypeBadge name={record.tipo_falta.nombre} />
              <span className="block truncate text-xs text-muted-foreground">{examLabel(record.examen)}</span>
            </span>

            <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          </Link>
        </li>
      ))}
    </ul>
  )
}
