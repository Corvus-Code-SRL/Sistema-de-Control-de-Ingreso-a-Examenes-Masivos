import { useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { finalReportService } from '../services/finalReportService'
import type { FinalReport } from '../types/finalReport.types'

function slugify(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

/**
 * Descarga del reporte final en PDF (HU-051). El archivo exige la sesión, por eso se pide con
 * el cliente de la API y se guarda desde el navegador en vez de usar un enlace directo.
 */
export function useFinalReportPdf(report: FinalReport | null) {
  const [downloading, setDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState<string | null>(null)

  const download = async () => {
    if (!report) return

    setDownloading(true)
    setDownloadError(null)

    try {
      const blob = await finalReportService.downloadPdf(report.cabecera.id_examen)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `reporte-final-${slugify(report.cabecera.nombre_examen)}-${report.cabecera.fecha}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch (error: unknown) {
      setDownloadError(error instanceof ApiError ? error.message : 'No se pudo descargar el PDF')
    } finally {
      setDownloading(false)
    }
  }

  return { download, downloading, downloadError }
}
