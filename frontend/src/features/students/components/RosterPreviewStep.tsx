import { CheckCircle2, FileText, Filter, Info, XCircle } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import type {
  RosterPreviewData,
  RosterPreviewRow,
} from '../types/roster.types'
import {
  countRosterRows,
  duplicateRowsBySis,
  isIncorporable,
  rowIssues,
} from '../utils/rosterRows'
import { RosterStatusLabel } from './RosterStatusLabel'

type PreviewFilter =
  | 'all'
  | 'incorporable'
  | 'already_in_group'
  | 'inconsistent'

interface RosterPreviewStepProps {
  subjectName: string
  groupLabel: string
  file: File | null
  preview: RosterPreviewData
  onSelectFile: (file: File) => void
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Paso 2: revisión completa del preview recibido desde el backend.
 */
export function RosterPreviewStep({
  subjectName,
  groupLabel,
  file,
  preview,
  onSelectFile,
  onCancel,
  onConfirm,
}: RosterPreviewStepProps) {
  const [filter, setFilter] = useState<PreviewFilter>('all')
  const inputRef = useRef<HTMLInputElement | null>(null)

  const counts = useMemo(
    () => countRosterRows(preview),
    [preview],
  )

  const duplicateRows = useMemo(
    () => duplicateRowsBySis(preview.filas),
    [preview.filas],
  )

  const filteredRows = useMemo(
    () =>
      preview.filas.filter((row) => {
        switch (filter) {
          case 'incorporable':
            return isIncorporable(row.estado)

          case 'already_in_group':
            return (
              row.estado === 'already_enrolled' ||
              row.estado === 'inactive_enrollment'
            )

          case 'inconsistent':
            return row.estado === 'inconsistent'

          case 'all':
          default:
            return true
        }
      }),
    [filter, preview.filas],
  )

  const handleFileChange = (
    event: React.ChangeEvent<HTMLInputElement>,
  ): void => {
    const selectedFile = event.currentTarget.files?.[0]

    if (selectedFile) {
      onSelectFile(selectedFile)
    }
  }

  return (
    <>
      <CardHeader className="border-b">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-1">
            <CardTitle className="sciem-h2">
              Previsualización
            </CardTitle>

            <p className="sciem-supporting text-muted-foreground">
              {subjectName} · {groupLabel}
            </p>
          </div>

          <div className="flex items-center gap-2">
            <input
              ref={inputRef}
              type="file"
              accept=".csv,.xlsx"
              className="sr-only"
              onChange={handleFileChange}
            />

            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => inputRef.current?.click()}
            >
              Cambiar archivo
            </Button>
          </div>
        </div>

        {file && (
          <div className="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
            <FileText aria-hidden="true" className="size-4 shrink-0" />
            <span className="truncate">{file.name}</span>
            <span aria-hidden="true">·</span>
            <span className="sciem-tnum shrink-0">
              {formatFileSize(file.size)}
            </span>
          </div>
        )}
      </CardHeader>

      <CardContent className="space-y-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <PreviewStat
            label="Filas leídas"
            value={counts.leidas}
            tone="neutral"
          />

          <PreviewStat
            label="Se incorporarán"
            value={counts.incorporables}
            tone="ok"
          />

          <PreviewStat
            label="Ya en el grupo"
            value={counts.yaEnElGrupo}
            tone="neutral"
          />

          <PreviewStat
            label="Inconsistencias"
            value={counts.inconsistentes}
            tone="danger"
          />
        </div>

        <Alert className="border-info-border bg-info-soft text-info">
          <Info aria-hidden="true" />

          <AlertDescription>
            Revisa todas las filas antes de continuar. Ningún registro cambia
            hasta la confirmación.
          </AlertDescription>
        </Alert>

        <div className="flex flex-wrap items-center gap-2">
          <Filter aria-hidden="true" className="size-4 text-muted-foreground" />

          <FilterButton
            active={filter === 'all'}
            onClick={() => setFilter('all')}
          >
            Todas
          </FilterButton>

          <FilterButton
            active={filter === 'incorporable'}
            onClick={() => setFilter('incorporable')}
          >
            Se incorporarán
          </FilterButton>

          <FilterButton
            active={filter === 'already_in_group'}
            onClick={() => setFilter('already_in_group')}
          >
            Ya en el grupo
          </FilterButton>

          <FilterButton
            active={filter === 'inconsistent'}
            onClick={() => setFilter('inconsistent')}
          >
            Con inconsistencias
          </FilterButton>
        </div>

        <div className="hidden overflow-x-auto rounded-xl border border-border-soft md:block">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="sciem-overline">Fila</TableHead>
                <TableHead className="sciem-overline">Código SIS</TableHead>
                <TableHead className="sciem-overline">Apellidos</TableHead>
                <TableHead className="sciem-overline">Nombres</TableHead>
                <TableHead className="sciem-overline">Estado</TableHead>
                <TableHead className="sciem-overline">
                  Observaciones
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              {filteredRows.map((row) => (
                <DesktopPreviewRow
                  key={row.numero_fila}
                  row={row}
                  duplicateRows={duplicateRows}
                />
              ))}
            </TableBody>
          </Table>

          {filteredRows.length === 0 && (
            <EmptyFilterState />
          )}
        </div>

        <div className="space-y-3 md:hidden">
          {filteredRows.length === 0 ? (
            <EmptyFilterState />
          ) : (
            filteredRows.map((row) => (
              <MobilePreviewCard
                key={row.numero_fila}
                row={row}
                duplicateRows={duplicateRows}
              />
            ))
          )}
        </div>
      </CardContent>

      <CardFooter className="flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
        >
          Cancelar
        </Button>

        <Button
          type="button"
          disabled={counts.incorporables === 0}
          onClick={onConfirm}
        >
          {counts.incorporables > 0
            ? `Continuar con ${counts.incorporables} estudiantes`
            : 'No hay estudiantes para incorporar'}
        </Button>
      </CardFooter>
    </>
  )
}

function DesktopPreviewRow({
  row,
  duplicateRows,
}: {
  row: RosterPreviewRow
  duplicateRows: Map<string, number[]>
}) {
  const issues = rowIssues(
    row,
    duplicateRows.get(row.codigo_sis ?? '') ?? [],
  )

  return (
    <TableRow>
      <TableCell className="sciem-tnum">{row.numero_fila}</TableCell>

      <TableCell className="sciem-tnum">
        {row.codigo_sis ?? '—'}
      </TableCell>

      <TableCell>{row.apellidos ?? '—'}</TableCell>

      <TableCell>{row.nombres ?? '—'}</TableCell>

      <TableCell>
        <RosterStatusLabel estado={row.estado} />
      </TableCell>

      <TableCell className="min-w-64 whitespace-normal">
        {issues.length > 0 ? (
          <IssueList issues={issues} />
        ) : (
          <span className="text-muted-foreground">Sin observaciones</span>
        )}
      </TableCell>
    </TableRow>
  )
}

function MobilePreviewCard({
  row,
  duplicateRows,
}: {
  row: RosterPreviewRow
  duplicateRows: Map<string, number[]>
}) {
  const issues = rowIssues(
    row,
    duplicateRows.get(row.codigo_sis ?? '') ?? [],
  )

  return (
    <article className="rounded-xl border border-border-soft bg-surface p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="sciem-overline text-muted-foreground">
            Fila {row.numero_fila}
          </p>

          <p className="sciem-tnum mt-1 font-medium">
            {row.codigo_sis ?? 'Sin código SIS'}
          </p>
        </div>

        <RosterStatusLabel estado={row.estado} />
      </div>

      <dl className="mt-4 grid gap-3">
        <div>
          <dt className="sciem-caption">Apellidos</dt>
          <dd className="sciem-body mt-0.5">
            {row.apellidos ?? '—'}
          </dd>
        </div>

        <div>
          <dt className="sciem-caption">Nombres</dt>
          <dd className="sciem-body mt-0.5">
            {row.nombres ?? '—'}
          </dd>
        </div>
      </dl>

      {issues.length > 0 && (
        <div className="mt-4 border-t border-border-soft pt-3">
          <p className="sciem-caption">Observaciones</p>
          <div className="mt-2">
            <IssueList issues={issues} />
          </div>
        </div>
      )}
    </article>
  )
}

function IssueList({
  issues,
}: {
  issues: ReturnType<typeof rowIssues>
}) {
  return (
    <div className="space-y-2">
      {issues.map((issue, index) => (
        <div
          key={`${issue.category}-${issue.message}-${index}`}
          className="flex items-start gap-2"
        >
          <IssueBadge category={issue.category} />

          <span className="sciem-supporting">
            {issue.message}
          </span>
        </div>
      ))}
    </div>
  )
}

function IssueBadge({
  category,
}: {
  category: string
}) {
  const className =
    category === 'Incompleto'
      ? 'border-warn-border bg-warn-soft text-warn-fg'
      : category === 'Formato'
        ? 'border-info-border bg-info-soft text-info'
        : category === 'Duplicado'
          ? 'border-danger/20 bg-danger-soft text-danger-fg'
          : 'border-border-soft bg-sunken text-muted-foreground'

  return (
    <Badge
      variant="outline"
      className={`shrink-0 ${className}`}
    >
      {category}
    </Badge>
  )
}

function PreviewStat({
  label,
  value,
  tone,
}: {
  label: string
  value: number
  tone: 'ok' | 'neutral' | 'danger'
}) {
  const toneClassName =
    tone === 'ok'
      ? 'bg-ok-soft text-ok-fg'
      : tone === 'danger'
        ? 'bg-danger-soft text-danger-fg'
        : 'bg-sunken text-foreground'

  return (
    <div className={`rounded-xl p-4 ${toneClassName}`}>
      <p className="sciem-caption">{label}</p>

      <p className="sciem-tnum mt-1 text-2xl font-semibold">
        {value}
      </p>
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
    <Button
      type="button"
      variant={active ? 'secondary' : 'ghost'}
      size="sm"
      aria-pressed={active}
      onClick={onClick}
    >
      {children}
    </Button>
  )
}

function EmptyFilterState() {
  return (
    <div className="rounded-xl border border-border-soft bg-sunken p-6 text-center">
      <XCircle
        aria-hidden="true"
        className="mx-auto size-5 text-muted-foreground"
      />

      <p className="sciem-label mt-2">No hay filas en este filtro</p>

      <p className="sciem-supporting mt-1 text-muted-foreground">
        Prueba otra categoría para continuar revisando la previsualización.
      </p>
    </div>
  )
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}