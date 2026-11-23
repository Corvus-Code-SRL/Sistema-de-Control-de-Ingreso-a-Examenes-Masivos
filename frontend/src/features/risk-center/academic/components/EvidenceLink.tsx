import { FileX, Paperclip } from 'lucide-react'
import { useEvidenceDownload, type EvidenceFetcher } from '../hooks/useEvidenceDownload'
import type { EvidenceInfo } from '../types/riskRectification.types'
import { formatFileSize } from '../utils/format'

interface EvidenceLinkProps {
  /** Id que entiende `fetcher`: el de la solicitud. */
  id: number
  evidence: EvidenceInfo
  fetcher: EvidenceFetcher
  label: string
}

/** Archivo descargable con la sesión; si ya no está en el servidor lo dice en lugar de ofrecer la descarga. */
export function EvidenceLink({ id, evidence, fetcher, label }: EvidenceLinkProps) {
  const { open, status, message } = useEvidenceDownload(id, evidence.nombre_archivo, fetcher)

  if (!evidence.disponible) {
    return (
      <p className="flex items-start gap-2 text-sm text-muted-foreground">
        <FileX className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>El archivo «{evidence.nombre_archivo}» no está disponible.</span>
      </p>
    )
  }

  return (
    <div className="space-y-1">
      <button
        type="button"
        onClick={open}
        disabled={status === 'loading'}
        className="flex items-center gap-2 text-left text-sm font-medium text-brand-deep underline-offset-4 hover:underline focus-visible:underline disabled:opacity-60"
      >
        <Paperclip className="size-4 shrink-0" aria-hidden="true" />
        <span className="break-all">{label}</span>
      </button>
      <p className="text-xs text-muted-foreground">
        {evidence.nombre_archivo}
        {evidence.tamano_bytes !== null && ` · ${formatFileSize(evidence.tamano_bytes)}`}
        {status === 'loading' && ' · Abriendo…'}
      </p>
      {status === 'error' && message && (
        <p role="alert" className="text-xs text-danger-fg">
          {message}
        </p>
      )}
    </div>
  )
}
