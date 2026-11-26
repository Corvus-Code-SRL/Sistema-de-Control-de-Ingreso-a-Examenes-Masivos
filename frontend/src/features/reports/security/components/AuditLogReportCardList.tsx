import { ActorLabel } from '@/features/audit-log/components/ActorLabel'
import { formatDateTime } from '@/features/audit-log/utils/format'

import type { AuditLogEntry } from '@/features/audit-log/types/auditLog.types'

interface AuditLogReportCardListProps {
  entries: AuditLogEntry[]
}

/** HU-068: read-only report cards for mobile devices. */
export function AuditLogReportCardList({
  entries,
}: AuditLogReportCardListProps) {
  return (
    <ul
      aria-label="Registros del reporte de bitácora"
      className="md:hidden"
    >
      {entries.map((entry) => (
        <li
          key={entry.id_log}
          className="space-y-3 border-b border-border px-4 py-3 last:border-b-0"
        >
          <p className="sciem-tnum text-xs text-muted-foreground">
            {formatDateTime(entry.fecha_hora)}
          </p>

          <ActorLabel actor={entry.usuario} />

          <dl className="grid grid-cols-2 gap-3">
            <div>
              <dt className="text-xs text-muted-foreground">
                Acción
              </dt>

              <dd className="text-sm font-medium">
                {entry.accion.etiqueta}
              </dd>
            </div>

            <div>
              <dt className="text-xs text-muted-foreground">
                Módulo
              </dt>

              <dd className="text-sm">
                {entry.modulo.etiqueta}
              </dd>
            </div>

            <div className="col-span-2">
              <dt className="text-xs text-muted-foreground">
                Registro afectado
              </dt>

              <dd className="break-words text-sm">
                {entry.registro.descripcion}
              </dd>
            </div>
          </dl>
        </li>
      ))}
    </ul>
  )
}