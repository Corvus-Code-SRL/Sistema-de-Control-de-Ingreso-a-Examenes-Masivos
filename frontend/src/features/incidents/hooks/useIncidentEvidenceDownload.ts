import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { getIncidentEvidence } from '../services/incidentsService'

const REVOKE_AFTER_MS = 60_000
const VIEWABLE_TYPE = /^(image\/(png|jpeg|gif|webp)|application\/pdf)$/

/**
 * Abre o descarga la evidencia de una incidencia con la sesión del docente.
 *
 * El archivo se pide con token, se abre desde una dirección local temporal y se revoca
 * a los 60 s. Nunca se enlaza por una URL pública.
 */
export function useIncidentEvidenceDownload(
  reportId: string,
  evidenceUrl: string,
  fileName: string
) {
  const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle')
  const [message, setMessage] = useState<string | null>(null)
  const controller = useRef<AbortController | null>(null)

  useEffect(() => () => controller.current?.abort(), [])

  const open = useCallback(async () => {
    controller.current?.abort()

    const current = new AbortController()
    controller.current = current

    setStatus('loading')
    setMessage(null)

    try {
      const file = await getIncidentEvidence(reportId, evidenceUrl, current.signal)
      const url = URL.createObjectURL(file)
      const link = document.createElement('a')

      link.href = url

      if (VIEWABLE_TYPE.test(file.type)) {
        link.target = '_blank'
        link.rel = 'noopener'
      } else {
        link.download = fileName
      }

      link.click()
      window.setTimeout(() => URL.revokeObjectURL(url), REVOKE_AFTER_MS)
      setStatus('idle')
    } catch (cause) {
      if (cause instanceof DOMException && cause.name === 'AbortError') return

      setMessage(cause instanceof ApiError ? cause.message : 'No se pudo abrir la evidencia.')
      setStatus('error')
    }
  }, [reportId, evidenceUrl, fileName])

  return { open, status, message }
}
