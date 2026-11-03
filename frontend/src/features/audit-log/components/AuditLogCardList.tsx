import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useDetailLink } from '../hooks/useDetailLink'
import type { AuditLogEntry } from '../types/auditLog.types'
import { formatDateTime } from '../utils/format'
import { ActorLabel } from './ActorLabel'

interface AuditLogCardListProps {
  entries: AuditLogEntry[]
}

/** Operaciones de la bitácora en móvil: una tarjeta por operación que abre el detalle. */
export function AuditLogCardList({ entries }: AuditLogCardListProps) {
  const detailLink = useDetailLink()

  return (
    <ul aria-label="Operaciones" className="md:hidden">
      {entries.map((entry) => (
        <li key={entry.id_log} className="border-b border-border last:border-b-0">
          <Link
            to={detailLink(entry.id_log)}
            aria-label={`Ver detalle de la operación ${entry.id_log}`}
            className="flex items-center gap-3 px-4 py-3 hover:bg-sunken focus-visible:bg-sunken"
          >
            <span className="min-w-0 flex-1 space-y-1">
              <span className="sciem-tnum block text-xs text-muted-foreground">
                {formatDateTime(entry.fecha_hora)}
              </span>
              <ActorLabel actor={entry.usuario} />
              <span className="block text-sm font-medium text-foreground">
                {entry.accion.etiqueta} · {entry.modulo.etiqueta}
              </span>
              <span className="block truncate text-xs text-muted-foreground">{entry.registro.descripcion}</span>
            </span>

            <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          </Link>
        </li>
      ))}
    </ul>
  )
}
