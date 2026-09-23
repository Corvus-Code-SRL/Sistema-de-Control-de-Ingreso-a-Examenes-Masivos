import { FileSpreadsheet, FileText } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  countRosterRows,
  duplicateRowsBySis,
  rowIssues,
} from '../utils/rosterRows'
import type { RosterPreviewData } from '../types/roster.types'

interface RosterStandalonePreviewProps {
  preview: RosterPreviewData
  fileName: string
  fileSize: number
  groupLabel: string
  onClear: () => void
}

/**
 * Preview de una nómina adjuntada al modal de "Nuevo grupo".
 *
 * Agrupa todo lo que el docente necesita ver antes de confirmar:
 * el archivo adjunto, los KPIs, la alerta de inconsistencias y la tabla
 * de filas rechazadas. Es específico de esta HU, por eso vive en un solo
 * componente en lugar de repartirse en subcomponentes reutilizables.
 */
export function RosterStandalonePreview({
  preview,
  fileName,
  fileSize,
  groupLabel,
  onClear,
}: RosterStandalonePreviewProps) {
  const counts = countRosterRows(preview)
  const duplicateRows = duplicateRowsBySis(preview.filas)
  const inconsistentRows = preview.filas.filter((row) => row.estado === 'inconsistent')

  return (
    <div className="space-y-3">
      {/* Archivo adjunto */}
      <div className="flex items-center gap-3 rounded-md border border-input bg-card px-3 py-2">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-soft text-brand">
          {fileName.toLowerCase().endsWith('.xlsx') ? (
            <FileSpreadsheet className="size-4" aria-hidden="true" />
          ) : (
            <FileText className="size-4" aria-hidden="true" />
          )}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium truncate">{fileName}</p>
          <p className="text-xs text-muted-foreground">{formatFileSize(fileSize)}</p>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={onClear}
          className="text-xs"
        >
          Quitar
        </Button>
      </div>

      {/* Grid de KPIs */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiBox label="Filas leídas" value={counts.leidas} />
        <KpiBox label="Válidas" value={counts.incorporables} />
        <KpiBox
          label="Con inconsistencias"
          value={counts.inconsistentes}
          tone={counts.inconsistentes > 0 ? 'danger' : 'neutral'}
        />
        <KpiBox label="Destino" value={groupLabel} />
      </div>

      {/* Alerta de inconsistencias */}
      {counts.inconsistentes > 0 && (
        <Alert className="border-[#F5C842]/40 bg-[#FEF7E0] text-[#8A6D00] py-2 px-3">
          <AlertDescription className="text-xs font-medium leading-tight">
            {counts.inconsistentes}{' '}
            {counts.inconsistentes === 1
              ? 'fila no se asociará al grupo'
              : 'filas no se asociarán al grupo'}
            . Solo se incorporan las {counts.incorporables} filas válidas.
          </AlertDescription>
        </Alert>
      )}

      {/* Tabla de inconsistencias */}
      {inconsistentRows.length > 0 && (
        <div className="max-h-64 overflow-x-auto overflow-y-auto rounded-md border border-input">
          <table className="w-full text-xs">
            <thead className="bg-muted/40 sticky top-0">
              <tr>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">Fila</th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">Código SIS</th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">Apellidos</th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">Nombres</th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">Motivo</th>
              </tr>
            </thead>
            <tbody>
              {inconsistentRows.map((row) => {
                const issues = rowIssues(
                  row,
                  duplicateRows.get(row.codigo_sis ?? '') ?? []
                )

                return (
                  <tr key={row.numero_fila} className="border-t border-input">
                    <td className="px-3 py-2 tabular-nums">{row.numero_fila}</td>
                    <td className="px-3 py-2 tabular-nums">{row.codigo_sis ?? '—'}</td>
                    <td className="px-3 py-2">{row.apellidos ?? '—'}</td>
                    <td className="px-3 py-2">{row.nombres ?? '—'}</td>
                    <td className="px-3 py-2">
                      <div className="flex flex-col gap-1">
                        {issues.map((issue, i) => (
                          <div key={i} className="flex items-center gap-1.5">
                            <span
                              className={`shrink-0 rounded-sm px-1.5 py-0.5 text-[10px] font-medium ${toneForCategory(issue.category)}`}
                            >
                              {issue.category}
                            </span>
                            <span className="text-muted-foreground">{issue.message}</span>
                          </div>
                        ))}
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

/* ============================================================
 * Helpers locales
 * ============================================================ */

function KpiBox({
  label,
  value,
  tone = 'neutral',
}: {
  label: string
  value: number | string
  tone?: 'neutral' | 'danger'
}) {
  return (
    <div className="rounded-md border border-input bg-muted/20 px-3 py-2">
      <p
        className={`text-lg font-semibold tabular-nums ${
          tone === 'danger' ? 'text-[#A21B12]' : 'text-[#2C2C2C]'
        }`}
      >
        {value}
      </p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  )
}

function toneForCategory(category: string): string {
  switch (category) {
    case 'Duplicado':
      return 'bg-[#FDE2E1] text-[#A21B12]'
    case 'Formato':
      return 'bg-[#E0F2FE] text-[#0369A1]'
    case 'Incompleto':
      return 'bg-[#FEF7E0] text-[#8A6D00]'
    default:
      return 'bg-muted text-muted-foreground'
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}