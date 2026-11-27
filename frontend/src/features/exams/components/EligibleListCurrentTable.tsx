import { useMemo, useState } from 'react'
import { AlertCircle, CheckCircle2, Search, XCircle } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

import type {
  EligibleStudent,
  EligibilityFilter,
  EligibilityStatus,
} from '../types/eligibleList.types'
import { eligibilityLabel, motivoLabel, normalizeText } from '../utils/eligibleListUtils'

interface EligibleListCurrentTableProps {
  students: EligibleStudent[]
  toolbar?: React.ReactNode
}

/**
 * Nómina del examen con su estado de habilitación, de solo lectura. Toda la lista vive en un
 * contenedor con scroll y cabecera fija; el buscador y los filtros trabajan sobre la lista completa.
 */
export function EligibleListCurrentTable({ students, toolbar }: EligibleListCurrentTableProps) {
  const [filter, setFilter] = useState<EligibilityFilter>('todos')
  const [search, setSearch] = useState('')

  const filtered = useMemo(() => {
    const normalizedSearch = normalizeText(search)
    return students.filter((s) => {
      if (filter === 'habilitados') {
        if (s.estado_habilitacion !== 'HABILITADO' && s.estado_habilitacion !== 'CASO_ESPECIAL') {
          return false
        }
      } else if (filter === 'no_habilitados') {
        if (s.estado_habilitacion !== 'NO_HABILITADO') return false
      }
      if (normalizedSearch.length === 0) return true
      return (
        normalizeText(s.nombre_completo).includes(normalizedSearch) ||
        normalizeText(s.codigo_sis).includes(normalizedSearch)
      )
    })
  }, [students, filter, search])

  const counts = useMemo(() => {
    const habilitados = students.filter(
      (s) => s.estado_habilitacion === 'HABILITADO' || s.estado_habilitacion === 'CASO_ESPECIAL'
    ).length
    const noHabilitados = students.filter((s) => s.estado_habilitacion === 'NO_HABILITADO').length
    return { total: students.length, habilitados, noHabilitados }
  }, [students])

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-1 rounded-lg bg-muted p-1">
          <FilterButton active={filter === 'todos'} onClick={() => setFilter('todos')}>
            Todos · {counts.total}
          </FilterButton>
          <FilterButton active={filter === 'habilitados'} onClick={() => setFilter('habilitados')}>
            Habilitados · {counts.habilitados}
          </FilterButton>
          <FilterButton active={filter === 'no_habilitados'} onClick={() => setFilter('no_habilitados')}>
            No habilitados · {counts.noHabilitados}
          </FilterButton>
        </div>

        <div className="relative w-full max-w-xs">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            aria-label="Buscar por nombre o código SIS"
            placeholder="Nombre o código SIS"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>

        {toolbar && <div className="ml-auto">{toolbar}</div>}
      </div>

      <div
        role="region"
        tabIndex={0}
        aria-label="Lista de estudiantes del examen"
        data-testid="eligible-scroll"
        className="max-h-[60vh] overflow-y-auto overscroll-contain rounded-xl border border-border-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:max-h-96"
      >
        <table className="hidden w-full caption-bottom text-sm md:table">
          <TableHeader>
            <TableRow>
              <StickyHead>Código SIS</StickyHead>
              <StickyHead>Estudiante</StickyHead>
              <StickyHead>Grupo</StickyHead>
              <StickyHead>Estado</StickyHead>
              <StickyHead>Motivo</StickyHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.map((student) => (
              <TableRow key={student.id_estudiante}>
                <TableCell className="sciem-tnum">{student.codigo_sis}</TableCell>
                <TableCell className="font-medium">{student.nombre_completo}</TableCell>
                <TableCell>{student.nombre_grupo}</TableCell>
                <TableCell>
                  <StatusBadge estado={student.estado_habilitacion} />
                </TableCell>
                <TableCell>
                  {student.motivo ? (
                    <span className="text-sm">
                      {motivoLabel(student.motivo)}
                      {student.detalle && ` · ${student.detalle}`}
                    </span>
                  ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </table>

        <div className="divide-y divide-border-soft md:hidden">
          {filtered.map((student) => (
            <div key={student.id_estudiante} className="flex items-start gap-2 p-3">
              <div className="flex-1">
                <p className="font-medium">{student.nombre_completo}</p>
                <p className="sciem-tnum text-sm text-muted-foreground">
                  {student.codigo_sis}
                  {student.nombre_grupo && ` · ${student.nombre_grupo}`}
                </p>
                {student.motivo && (
                  <p className="mt-1 text-sm">
                    {motivoLabel(student.motivo)}
                    {student.detalle && ` · ${student.detalle}`}
                  </p>
                )}
              </div>
              <StatusBadge estado={student.estado_habilitacion} />
            </div>
          ))}
        </div>

        {filtered.length === 0 && (
          <div className="p-6 text-center text-sm text-muted-foreground">
            No hay estudiantes que coincidan con los filtros.
          </div>
        )}
      </div>

      <p className="text-xs text-muted-foreground">
        {filtered.length === counts.total
          ? `${counts.total} estudiantes en la lista.`
          : `${filtered.length} de ${counts.total} estudiantes.`}
      </p>
    </div>
  )
}

function StickyHead({ children }: { children: React.ReactNode }) {
  return <TableHead className="sticky top-0 z-10 bg-background shadow-[0_1px_0_0] shadow-border-soft">{children}</TableHead>
}

function FilterButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
        active
          ? 'bg-background text-foreground shadow-sm border border-border-soft'
          : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  )
}

function StatusBadge({ estado }: { estado: EligibilityStatus }) {
  const variant =
    estado === 'HABILITADO'
      ? 'bg-ok-soft text-ok-fg border-ok-border'
      : estado === 'NO_HABILITADO'
        ? 'bg-danger-soft text-danger-fg border-danger-border'
        : 'bg-warn-soft text-warn-fg border-warn-border'

  const Icon =
    estado === 'HABILITADO' ? CheckCircle2 : estado === 'NO_HABILITADO' ? XCircle : AlertCircle

  return (
    <Badge variant="outline" className={`gap-1 ${variant}`}>
      <Icon className="size-3.5" aria-hidden="true" />
      {eligibilityLabel(estado)}
    </Badge>
  )
}
