import { ShieldAlert } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { initialsOf } from '@/features/risk-center/academic/utils/format'
import type { AlienAttemptsStudent } from '../types/alienAttempts.types'

interface AlienAttemptsTableProps {
  records: AlienAttemptsStudent[]
}

/** Nombre del examen tal como lo muestra la celda: «1er Parcial Cálculo I · 2do Parcial Álgebra I». */
export function examenesLabel(examenes: string[]): string {
  return examenes.join(' · ')
}

/**
 * El badge «En CR» es un enlace al detalle de la Central de Riesgo cuando hay registro.
 * Decisión de equipo (HU-063): el mockup no lo muestra como enlace, pero se acordó
 * para evitar un salto manual a la CR.
 */
function CentralRiesgoBadge({ idCr, nombre }: { idCr: number | null; nombre: string }) {
  if (idCr === null) {
    return <span className="text-muted-foreground">—</span>
  }

  return (
    <Link
      to={`/central-riesgo/${idCr}`}
      aria-label={`Ver registro en la Central de Riesgo de ${nombre}`}
      title="Ver en la Central de Riesgo"
      className="inline-flex rounded-full transition-opacity hover:opacity-85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <Badge variant="destructive" className="gap-1">
        <ShieldAlert className="size-3" aria-hidden="true" />
        En CR
      </Badge>
    </Link>
  )
}

/** Filas del reporte en escritorio. Una fila por estudiante agrupado. */
export function AlienAttemptsTable({ records }: AlienAttemptsTableProps) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="sciem-overline text-muted-foreground">Estudiante</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Código SIS</TableHead>
          <TableHead className="sciem-overline text-right text-muted-foreground">Intentos</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Exámenes</TableHead>
          <TableHead className="sciem-overline text-muted-foreground">Central de Riesgo</TableHead>
        </TableRow>
      </TableHeader>

      <TableBody>
        {records.map((record) => (
          <TableRow key={record.id_estudiante}>
            <TableCell>
              <div className="flex items-center gap-2.5">
                <Avatar className="size-8">
                  <AvatarFallback
                    className="bg-brand-soft text-xs font-semibold text-brand-deep"
                    aria-hidden="true"
                  >
                    {initialsOf(record.nombre_completo)}
                  </AvatarFallback>
                </Avatar>
                <span className="font-medium">{record.nombre_completo}</span>
              </div>
            </TableCell>

            <TableCell className="sciem-tnum text-muted-foreground">{record.cod_sis}</TableCell>

            <TableCell className="sciem-tnum text-right font-semibold">{record.intentos}</TableCell>

            <TableCell className="max-w-md truncate" title={examenesLabel(record.examenes)}>
              {examenesLabel(record.examenes)}
            </TableCell>

            <TableCell>
              <CentralRiesgoBadge idCr={record.id_cr_estudiante} nombre={record.nombre_completo} />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
