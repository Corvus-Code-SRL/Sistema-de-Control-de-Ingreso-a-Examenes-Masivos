import { useEffect, useState } from 'react'
import { Download, FileText } from 'lucide-react'

import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Toast } from '@/components/ui/toast'

import { useAuditLogOptions } from '@/features/audit-log/hooks/useAuditLogOptions'

import { AuditLogReportFilters } from '../components/AuditLogReportFilters'
import { AuditLogReportStats } from '../components/AuditLogReportStats'
import { AuditLogReportTable } from '../components/AuditLogReportTable'
import { AuditLogReportCardList } from '../components/AuditLogReportCardList'

import { useAuditLogReport } from '../hooks/useAuditLogReport'

import {
  useAuditLogReportFilters,
  defaultReportFilters,
  rangeError,
} from '../hooks/useAuditLogReportFilters'

import {
  getAuditLogReportPdf,
  getAuditLogReportExcel,
} from '../services/auditLogReportService'

import type {
  AuditLogReportExportFilters,
} from '../types/auditLogReport.types'

type ExportFormat = 'pdf' | 'excel'

/** HU-068: administrative audit log report. */
export function AuditLogReportPage() {
  const {
    filters,
    applyFilters,
    setPage,
    clear,
  } = useAuditLogReportFilters()

  const {
    options,
    status: optionsStatus,
  } = useAuditLogOptions()

  const {
    page,
    status,
    error,
    reload,
  } = useAuditLogReport(filters)

  const { desde, hasta, q, accion, modulo } = filters

  const [draft, setDraft] =
    useState<AuditLogReportExportFilters>({
      desde,
      hasta,
      q,
      accion,
      modulo,
    })

  const [exporting, setExporting] =
    useState<ExportFormat | null>(null)

  const [exportError, setExportError] =
    useState<string | null>(null)

  useEffect(() => {
    setDraft({
      desde,
      hasta,
      q,
      accion,
      modulo,
    })
  }, [desde, hasta, q, accion, modulo])

  const validationError = rangeError(
    draft.desde,
    draft.hasta
  )

  const generating = status === 'loading'

  const dirty =
    draft.desde !== desde ||
    draft.hasta !== hasta ||
    draft.q.trim() !== q ||
    draft.accion !== accion ||
    draft.modulo !== modulo

  const settled =
    status === 'success' && page !== null

  const canExport =
    settled &&
    page.canExport &&
    !dirty &&
    !validationError &&
    exporting === null

  useEffect(() => {
    if (
      status === 'success' &&
      page !== null &&
      filters.page > page.totalPages
    ) {
      setPage(page.totalPages, true)
    }
  }, [status, page, filters.page, setPage])

  function handleGenerate() {
    if (validationError || generating) return

    if (!dirty && filters.page === 1) {
      reload()
      return
    }

    applyFilters(draft)
  }

  function handleClear() {
    const defaults = defaultReportFilters()

    setDraft({
      desde: defaults.desde,
      hasta: defaults.hasta,
      q: defaults.q,
      accion: defaults.accion,
      modulo: defaults.modulo,
    })

    clear()
  }

  async function handleDownload(format: ExportFormat) {
    if (!canExport || !page) return

    setExporting(format)
    setExportError(null)

    const exportFilters: AuditLogReportExportFilters = {
      desde: page.filters.desde,
      hasta: page.filters.hasta,
      q: page.filters.q ?? '',
      accion: page.filters.accion,
      modulo: page.filters.modulo,
    }

    try {
      const blob =
        format === 'pdf'
          ? await getAuditLogReportPdf(exportFilters)
          : await getAuditLogReportExcel(exportFilters)

      const extension =
        format === 'pdf' ? 'pdf' : 'xlsx'

      const start =
        exportFilters.desde.replace(/-/g, '')

      const end =
        exportFilters.hasta.replace(/-/g, '')

      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')

      anchor.href = url
      anchor.download =
        `reporte-bitacora-${start}-${end}.${extension}`

      document.body.appendChild(anchor)

      try {
        anchor.click()
      } finally {
        anchor.remove()
        URL.revokeObjectURL(url)
      }
    } catch {
      setExportError(
        format === 'pdf'
          ? 'No se pudo descargar el PDF. Intente nuevamente.'
          : 'No se pudo descargar el Excel. Intente nuevamente.'
      )
    } finally {
      setExporting(null)
    }
  }

  return (
    <AppShell
      mobileTitle="Reporte de bitácora"
      breadcrumbs={[
        { label: 'Administración' },
        { label: 'Bitácora' },
        { label: 'Reporte' },
      ]}
    >
      <PageHeader
        title="Generar reporte de bitácora"
        subtitle="Consulte y exporte las operaciones registradas en el sistema."
        actions={
          <>
            <Button
              variant="outline"
              disabled={!canExport}
              onClick={() => handleDownload('pdf')}
            >
              <Download
                className="size-4"
                aria-hidden="true"
              />

              {exporting === 'pdf'
                ? 'Descargando PDF...'
                : 'Descargar PDF'}
            </Button>

            <Button
              variant="outline"
              disabled={!canExport}
              onClick={() => handleDownload('excel')}
            >
              <Download
                className="size-4"
                aria-hidden="true"
              />

              {exporting === 'excel'
                ? 'Descargando Excel...'
                : 'Descargar Excel'}
            </Button>
          </>
        }
      />

      <div className="space-y-4">
        <Card className="p-4">
          <AuditLogReportFilters
            draft={draft}
            options={options}
            optionsUnavailable={optionsStatus === 'error'}
            rangeError={validationError}
            generating={generating}
            onChange={setDraft}
            onGenerate={handleGenerate}
            onClear={handleClear}
          />

          {dirty && !validationError && (
            <p
              role="status"
              className="mt-3 text-sm text-muted-foreground"
            >
              Presione Generar reporte para aplicar los cambios.
            </p>
          )}
        </Card>

        {settled && (
          <AuditLogReportStats totals={page.totals} />
        )}

        <Card
          className="overflow-hidden p-0"
          aria-busy={generating}
        >
          {generating && (
            <LoadingState
              rows={6}
              label="Cargando reporte"
            />
          )}

          {status === 'error' && error && (
            <ErrorState
              error={error}
              onRetry={reload}
            />
          )}

          {settled && page.total === 0 && (
            <EmptyState
              icon={FileText}
              title="Sin operaciones en este rango"
              description="No se encontraron operaciones que coincidan con los filtros seleccionados."
            />
          )}

          {settled && page.items.length > 0 && (
            <>
              <div className="hidden md:block">
                <AuditLogReportTable
                  entries={page.items}
                />
              </div>

              <AuditLogReportCardList
                entries={page.items}
              />

              <DataPagination
                page={page.page}
                perPage={page.perPage}
                total={page.total}
                totalPages={page.totalPages}
                onPageChange={(next) => setPage(next)}
                itemLabel="registros"
                disabled={generating || dirty}
              />
            </>
          )}
        </Card>
      </div>

      {exportError !== null && (
        <Toast
          title="Error de descarga"
          description={exportError}
          variant="error"
          duration={6000}
          onClose={() => setExportError(null)}
        />
      )}
    </AppShell>
  )
}