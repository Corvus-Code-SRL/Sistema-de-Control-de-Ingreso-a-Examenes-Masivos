import { useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { finalReportService } from '../services/finalReportService'
import type { FinalReport } from '../types/finalReport.types'
import { reportFileName } from '../utils/reportFileName'

/** Descarga del reporte final en Excel (HU-051), con la misma sesión que el PDF. */
export function useFinalReportExcel(report: FinalReport | null) {
  const [downloading, setDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState<string | null>(null)

  const download = async () => {
    if (!report) return

    setDownloading(true)
    setDownloadError(null)

    try {
      const blob = await finalReportService.downloadExcel(report.cabecera.id_examen)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = reportFileName(report.cabecera.nombre_examen, report.cabecera.fecha, 'xlsx')
      link.click()
      URL.revokeObjectURL(url)
    } catch (error: unknown) {
      setDownloadError(error instanceof ApiError ? error.message : 'No se pudo descargar el Excel')
    } finally {
      setDownloading(false)
    }
  }

  return { download, downloading, downloadError }
}
