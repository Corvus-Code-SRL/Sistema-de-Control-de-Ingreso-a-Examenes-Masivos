import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatPlainDate } from '../utils/format'
import type { RectifiedRecord } from '../types/riskRectification.types'
import { FaultTypeBadge } from './FaultTypeBadge'

interface RectifiedRecordListProps {
  records: RectifiedRecord[]
}

/** Registros ya retirados de la Central: solo se consultan, sin ningún control. */
export function RectifiedRecordList({ records }: RectifiedRecordListProps) {
  return (
    <Table aria-label="Registros rectificados del estudiante">
      <TableHeader>
        <TableRow>
          <TableHead>Fecha del registro</TableHead>
          <TableHead>Examen</TableHead>
          <TableHead>Tipo de falta</TableHead>
          <TableHead>Rectificado el</TableHead>
          <TableHead>Administrador</TableHead>
          <TableHead>Motivo</TableHead>
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
            <TableCell className="whitespace-nowrap">{formatDate(record.rectificacion.fecha)}</TableCell>
            <TableCell>{record.rectificacion.administrador.nombre_completo ?? 'Sin dato'}</TableCell>
            <TableCell className="max-w-xs whitespace-pre-line break-words">
              {record.rectificacion.motivo}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
