import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { getRiskCenterEvidence } from '../services/riskCenterService'

const REVOKE_AFTER_MS = 60_000

/** Tipos que el navegador muestra en una pestaña; el resto se descarga. */
const VIEWABLE_TYPE = /^(image\/(png|jpeg|gif|webp)|application\/pdf)$/

/**
 * Abre o descarga la evidencia de un registro.
 *
 * El archivo solo se entrega con la sesión, así que se pide como Blob con el token y se abre desde
 * una dirección local temporal; nunca se enlaza por una URL pública.
 */
export function useEvidenceDownload(recordId: number, fileName: string) {
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
      const file = await getRiskCenterEvidence(recordId, current.signal)
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
  }, [recordId, fileName])

  return { open, status, message }
}
