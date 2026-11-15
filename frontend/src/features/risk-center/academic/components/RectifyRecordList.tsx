import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatPlainDate } from '../utils/format'
import type { RectifiableRecord } from '../types/riskRectification.types'
import { FaultTypeBadge } from './FaultTypeBadge'
import { RectificationStateBadge } from './RectificationStateBadge'

interface RectifyRecordListProps {
  records: RectifiableRecord[]
  onRectify: (record: RectifiableRecord) => void
}

/** Registros confirmados del estudiante, con su estado y el botón para rectificar cada uno. Sin borrar. */
export function RectifyRecordList({ records, onRectify }: RectifyRecordListProps) {
  return (
    <Table aria-label="Registros confirmados del estudiante">
      <TableHeader>
        <TableRow>
          <TableHead>Fecha</TableHead>
          <TableHead>Examen</TableHead>
          <TableHead>Tipo de falta</TableHead>
          <TableHead>Aprobó</TableHead>
          <TableHead>Estado</TableHead>
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
            <TableCell>{record.aprobador?.nombre_completo ?? 'Sin dato'}</TableCell>
            <TableCell>
              <RectificationStateBadge
                state={record.estado}
                count={record.numero_rectificaciones}
                lastAt={record.ultima_rectificacion}
              />
            </TableCell>
            <TableCell className="text-right">
              <Button
                type="button"
                size="sm"
                variant="outline"
                aria-label={`Rectificar registro del examen ${record.examen.nombre}, ${formatDate(record.fecha_registro)}`}
                onClick={() => onRectify(record)}
              >
                Rectificar
              </Button>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
