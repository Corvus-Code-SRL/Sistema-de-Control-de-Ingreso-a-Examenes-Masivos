import { useMemo, useState } from 'react'
import { AlertCircle, CheckCircle2, Search, XCircle } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import type {
  EligibleStudent,
  EligibilityFilter,
  EligibilityStatus,
} from '../types/eligibleList.types'
import { eligibilityLabel, motivoLabel, normalizeText } from '../utils/eligibleListUtils'

const PAGE_SIZE = 50

interface EligibleListCurrentTableProps {
  students: EligibleStudent[]
  selectedIds: Set<number>
  onSelectionChange: (ids: Set<number>) => void
  editable: boolean
  toolbar?: React.ReactNode
  hasPendingChange?: (id: number) => boolean
}

export function EligibleListCurrentTable({
  students,
  selectedIds,
  onSelectionChange,
  editable,
  toolbar,
  hasPendingChange,
}: EligibleListCurrentTableProps) {
  const [filter, setFilter] = useState<EligibilityFilter>('todos')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

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

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const currentPage = Math.min(page, totalPages)
  const paginated = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE)

  const selectableIds = useMemo(
    () => paginated.filter((s) => !s.caso_especial).map((s) => s.id_estudiante),
    [paginated]
  )

  const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.has(id))

  const toggleAll = (): void => {
    const next = new Set(selectedIds)
    if (allSelected) {
      selectableIds.forEach((id) => next.delete(id))
    } else {
      selectableIds.forEach((id) => next.add(id))
    }
    onSelectionChange(next)
  }

  const toggleOne = (id: number): void => {
    const next = new Set(selectedIds)
    if (next.has(id)) {
      next.delete(id)
    } else {
      next.add(id)
    }
    onSelectionChange(next)
  }

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
          <FilterButton active={filter === 'todos'} onClick={() => { setFilter('todos'); setPage(1) }}>
            Todos · {counts.total}
          </FilterButton>
          <FilterButton active={filter === 'habilitados'} onClick={() => { setFilter('habilitados'); setPage(1) }}>
            Habilitados · {counts.habilitados}
          </FilterButton>
          <FilterButton active={filter === 'no_habilitados'} onClick={() => { setFilter('no_habilitados'); setPage(1) }}>
            No habilitados · {counts.noHabilitados}
          </FilterButton>
        </div>

        <div className="relative w-full max-w-xs">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            aria-label="Buscar por nombre o código SIS"
            placeholder="Nombre o código SIS"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            className="pl-9"
          />
        </div>

        {toolbar && <div className="ml-auto">{toolbar}</div>}
      </div>

      {/* #3: en desktop tabla; en móvil, cards por estudiante */}
      <div className="hidden overflow-hidden rounded-xl border border-border-soft md:block">
        <Table>
          <TableHeader>
            <TableRow>
              {editable && (
                <TableHead className="w-12">
                  <input
                    type="checkbox"
                    aria-label="Marcar todos los estudiantes"
                    checked={allSelected}
                    onChange={toggleAll}
                    className="size-4 rounded border-border-strong"
                  />
                </TableHead>
              )}
              <TableHead>Código SIS</TableHead>
              <TableHead>Estudiante</TableHead>
              <TableHead>Grupo</TableHead>
              <TableHead>Estado</TableHead>
              <TableHead>Motivo</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginated.map((student) => {
              const pending = hasPendingChange?.(student.id_estudiante) ?? false
              return (
                <TableRow key={student.id_estudiante} data-selected={selectedIds.has(student.id_estudiante)}>
                  {editable && (
                    <TableCell>
                      <input
                        type="checkbox"
                        aria-label={`Marcar a ${student.nombre_completo}`}
                        checked={selectedIds.has(student.id_estudiante)}
                        onChange={() => toggleOne(student.id_estudiante)}
                        disabled={student.caso_especial}
                        className="size-4 rounded border-border-strong"
                      />
                    </TableCell>
                  )}
                  <TableCell className="sciem-tnum">{student.codigo_sis}</TableCell>
                  <TableCell className="font-medium">{student.nombre_completo}</TableCell>
                  <TableCell>{student.nombre_grupo}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <StatusBadge estado={student.estado_habilitacion} />
                      {/* #7: aviso de cambio sin guardar */}
                      {pending && (
                        <span className="text-xs text-warn-fg">· sin guardar</span>
                      )}
                    </div>
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
              )
            })}
          </TableBody>
        </Table>
      </div>

      {/* #3: vista móvil */}
      <div className="space-y-2 md:hidden">
        {paginated.map((student) => {
          const pending = hasPendingChange?.(student.id_estudiante) ?? false
          return (
            <div
              key={student.id_estudiante}
              className="rounded-xl border border-border-soft p-3"
              data-selected={selectedIds.has(student.id_estudiante)}
            >
              <div className="flex items-start gap-2">
                {editable && (
                  <input
                    type="checkbox"
                    aria-label={`Marcar a ${student.nombre_completo}`}
                    checked={selectedIds.has(student.id_estudiante)}
                    onChange={() => toggleOne(student.id_estudiante)}
                    disabled={student.caso_especial}
                    className="mt-1 size-4 rounded border-border-strong"
                  />
                )}
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
                  {pending && (
                    <p className="mt-1 text-xs text-warn-fg">· sin guardar</p>
                  )}
                </div>
                <div className="flex flex-col items-end gap-1">
                  <StatusBadge estado={student.estado_habilitacion} />
                </div>
              </div>
            </div>
          )
        })}
      </div>

      {paginated.length === 0 && (
        <div className="p-6 text-center text-sm text-muted-foreground">
          No hay estudiantes que coincidan con los filtros.
        </div>
      )}

      {totalPages > 1 && (
        <nav
           aria-label="Paginación de estudiantes"
           className="flex flex-col gap-2 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
          <span>
            {filtered.length} estudiantes · Página {currentPage} de {totalPages}
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === 1}
              onClick={() => setPage(currentPage - 1)}
            >
              Anterior
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === totalPages}
              onClick={() => setPage(currentPage + 1)}
            >
              Siguiente
            </Button>
          </div>
        </nav>
      )}
    </div>
  )
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
