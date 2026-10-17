import { Eye } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { buttonVariants } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'
import type { RiskCenterRecordSummary } from '../types/riskCenter.types'
import { formatDate, initialsOf } from '../utils/format'
import { FaultTypeBadge } from './FaultTypeBadge'
import { useDetailLink } from '../hooks/useDetailLink'

interface RiskCenterTableProps {
  records: RiskCenterRecordSummary[]
}

/** Texto del examen tal como lo muestra la lista: «1er Parcial · Cálculo I». */
export function examLabel(exam: RiskCenterRecordSummary['examen']): string {
  return exam.materia ? `${exam.nombre} · ${exam.materia}` : exam.nombre
}

/**
 * Registros de la Central de Riesgo en escritorio. Solo se puede abrir el detalle: la consulta es de
 * solo lectura y no hay acciones de edición ni de borrado.
 */
export function RiskCenterTable({ records }: RiskCenterTableProps) {
  const detailLink = useDetailLink()

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Estudiante</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Código SIS</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Tipo de falta</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Examen</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Registro</TableHead>
          <TableHead>
            <span className="sr-only">Detalle</span>
          </TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {records.map((record) => (
          <TableRow key={record.id_cr_estudiante}>
            <TableCell>
              <div className="flex items-center gap-2.5">
                <Avatar className="size-8">
                  <AvatarFallback className="bg-brand-soft text-xs font-semibold text-brand-deep" aria-hidden="true">
                    {initialsOf(record.estudiante.nombre_completo)}
                  </AvatarFallback>
                </Avatar>
                <span className="font-medium">{record.estudiante.nombre_completo}</span>
              </div>
            </TableCell>

            <TableCell className="sciem-tnum text-muted-foreground">{record.estudiante.cod_sis}</TableCell>

            <TableCell>
              <FaultTypeBadge name={record.tipo_falta.nombre} />
            </TableCell>

            <TableCell>{examLabel(record.examen)}</TableCell>

            <TableCell className="sciem-tnum text-muted-foreground">{formatDate(record.fecha_registro)}</TableCell>

            <TableCell className="text-right">
              <Link
                to={detailLink(record.id_cr_estudiante)}
                aria-label={`Ver registro de ${record.estudiante.nombre_completo}`}
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
