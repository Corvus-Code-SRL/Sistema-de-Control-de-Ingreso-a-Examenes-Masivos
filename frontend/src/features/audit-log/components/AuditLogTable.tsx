import { Eye } from 'lucide-react'
import { Link } from 'react-router-dom'
import { buttonVariants } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'
import { useDetailLink } from '../hooks/useDetailLink'
import type { AuditLogEntry } from '../types/auditLog.types'
import { formatDateTime } from '../utils/format'
import { ActorLabel } from './ActorLabel'

interface AuditLogTableProps {
  entries: AuditLogEntry[]
}

/**
 * Operaciones de la bitácora en escritorio. Lo único que se puede hacer es abrir el detalle: la bitácora es
 * de solo lectura y no hay acciones de edición ni de borrado.
 */
export function AuditLogTable({ entries }: AuditLogTableProps) {
  const detailLink = useDetailLink()

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Fecha y hora</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Usuario</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Acción</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Módulo</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Registro afectado</TableHead>
          <TableHead>
            <span className="sr-only">Detalle</span>
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

            <TableCell className="font-medium">{entry.accion.etiqueta}</TableCell>

            <TableCell>{entry.modulo.etiqueta}</TableCell>

            <TableCell className="max-w-64 truncate" title={entry.registro.descripcion}>
              {entry.registro.descripcion}
            </TableCell>

            <TableCell className="text-right">
              <Link
                to={detailLink(entry.id_log)}
                aria-label={`Ver detalle de la operación ${entry.id_log}`}
                className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
              >
                Ver
                <Eye className="size-4" aria-hidden="true" />
              </Link>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
