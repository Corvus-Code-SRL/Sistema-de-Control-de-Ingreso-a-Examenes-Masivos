import { ShieldCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatPlainDate } from '../utils/format'
import type { RectifiableRecord } from '../types/riskRectification.types'
import { FaultTypeBadge } from './FaultTypeBadge'

interface RectifyRecordListProps {
  records: RectifiableRecord[]
  onRectify: (record: RectifiableRecord) => void
}

/** Registros activos del estudiante, cada uno con su botón verde «Rectificar» y, si la hay, su solicitud en espera. */
export function RectifyRecordList({ records, onRectify }: RectifyRecordListProps) {
  return (
    <Table aria-label="Registros activos del estudiante">
      <TableHeader>
        <TableRow>
          <TableHead>Fecha</TableHead>
          <TableHead>Examen</TableHead>
          <TableHead>Tipo de falta</TableHead>
          <TableHead>Aprobó</TableHead>
          <TableHead>
            <span className="sr-only">Acción</span>
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {records.map((record) => (
          <TableRow key={record.id_cr_estudiante}>
            <TableCell className="whitespace-nowrap">{formatDate(record.fecha_registro)}</TableCell>
            <TableCell>
              <span className="block font-medium">{record.examen.nombre}</span>
              <span className="block text-xs text-muted-foreground">
                {record.examen.materia === null ? '' : `${record.examen.materia} · `}
                {formatPlainDate(record.examen.fecha)}
              </span>
            </TableCell>
            <TableCell>
              <FaultTypeBadge name={record.tipo_falta.nombre} />
            </TableCell>
            <TableCell>
              {record.aprobador?.nombre_completo ?? 'Sin dato'}
              {record.rectificacion?.solicitud_pendiente != null && (
                <Badge variant="outline" className="mt-1 block w-fit border-warn-border bg-warn-soft text-warn-fg">
                  Esperando confirmación del docente
                </Badge>
              )}
            </TableCell>
            <TableCell className="text-right">
              <Button
                type="button"
                variant="success"
                size="xl"
                aria-label={`Rectificar registro del examen ${record.examen.nombre}, ${formatDate(record.fecha_registro)}`}
                onClick={() => onRectify(record)}
              >
                <ShieldCheck aria-hidden="true" />
                Rectificar
              </Button>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
