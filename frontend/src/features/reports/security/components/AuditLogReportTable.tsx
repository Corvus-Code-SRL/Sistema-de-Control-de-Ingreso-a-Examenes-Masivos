import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import { ActorLabel, formatDateTime } from '@/features/audit-log'

import type { AuditLogEntry } from '@/features/audit-log'

interface AuditLogReportTableProps {
  entries: AuditLogEntry[]
}

/** HU-068: read-only report table for desktop. */
export function AuditLogReportTable({
  entries,
}: AuditLogReportTableProps) {
  return (
    <Table aria-label="Registros del reporte de bitácora">
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">
            Fecha y hora
          </TableHead>

          <TableHead className="sciem-overline text-muted-foreground">
            Usuario
          </TableHead>

          <TableHead className="sciem-overline text-muted-foreground">
            Acción
          </TableHead>

          <TableHead className="sciem-overline text-muted-foreground">
            Módulo
          </TableHead>

          <TableHead className="sciem-overline text-muted-foreground">
            Registro afectado
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {entries.map((entry) => (
          <TableRow key={entry.id_log}>
            <TableCell className="sciem-tnum whitespace-nowrap text-muted-foreground">
              {formatDateTime(entry.fecha_hora)}
            </TableCell>

            <TableCell>
              <ActorLabel actor={entry.usuario} />
            </TableCell>

            <TableCell className="font-medium">
              {entry.accion.etiqueta}
            </TableCell>

            <TableCell>
              {entry.modulo.etiqueta}
            </TableCell>

            <TableCell
              className="max-w-64 whitespace-normal break-words"
              title={entry.registro.descripcion}
            >
              {entry.registro.descripcion}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}