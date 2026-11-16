import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import {
  eligibleListService,
  validateEligibleFile,
} from '../services/eligibleListService'
import type {
  NoEligibleReason,
  UploadConfirmData,
  UploadPreviewData,
} from '../types/eligibleList.types'

export type UploadStep = 'idle' | 'previewing' | 'preview' | 'confirming' | 'result'

export interface UseEligibleListUploadResult {
  step: UploadStep
  file: File | null
  preview: UploadPreviewData | null
  result: UploadConfirmData | null
  errorMessage: string | null
  startUpload: (file: File) => void
  retry: () => void
  applyToPending: (motivo: NoEligibleReason, detalle?: string) => Promise<UploadConfirmData | null>
  reset: () => void
}

const NETWORK_FALLBACK = 'No se pudo conectar con el servidor. Revise su conexión.'

export function useEligibleListUpload(examId: number | null): UseEligibleListUploadResult {
  const [step, setStep] = useState<UploadStep>('idle')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<UploadPreviewData | null>(null)
  const [result, setResult] = useState<UploadConfirmData | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const requestRef = useRef<AbortController | null>(null)
  const mountedRef = useRef(true)

  useEffect(() => {
    mountedRef.current = true
    return () => {
      mountedRef.current = false
      requestRef.current?.abort()
    }
  }, [])

  const startRequest = useCallback((): AbortController => {
    requestRef.current?.abort()
    const controller = new AbortController()
    requestRef.current = controller
    return controller
  }, [])

  const isCurrent = useCallback(
    (controller: AbortController): boolean =>
      mountedRef.current && requestRef.current === controller,
    []
  )

  const runPreview = useCallback(
    async (selectedFile: File): Promise<void> => {
      if (examId === null) return

      const rejection = validateEligibleFile(selectedFile)
      if (rejection !== null) {
        setErrorMessage(rejection)
        setStep('idle')
        return
      }

      const controller = startRequest()
      setErrorMessage(null)
      setStep('previewing')

      try {
        const data = await eligibleListService.previewUpload(examId, selectedFile, controller.signal)
        if (!isCurrent(controller)) return
        setPreview(data)
        setStep('preview')
      } catch (cause) {
        if (!isCurrent(controller)) return
        setPreview(null)
        setErrorMessage(toApiError(cause).message ?? NETWORK_FALLBACK)
        setStep('idle')
      }
    },
    [examId, isCurrent, startRequest]
  )

  const startUpload = useCallback(
    (selectedFile: File): void => {
      setFile(selectedFile)
      setResult(null)
      void runPreview(selectedFile)
    },
    [runPreview]
  )

  const retry = useCallback((): void => {
    if (file !== null) void runPreview(file)
  }, [file, runPreview])

  const applyToPending = useCallback(
    async (motivo: NoEligibleReason, detalle?: string): Promise<UploadConfirmData | null> => {
      if (examId === null || preview === null) return null

      const controller = startRequest()
      setErrorMessage(null)
      setStep('confirming')

      try {
        const data = await eligibleListService.confirmUpload(
          examId,
          preview.token,
          motivo,
          detalle,
          controller.signal
        )
        if (!isCurrent(controller)) return null
          setResult(data)
          setStep('result')
          return data
      } catch (cause) {
        if (!isCurrent(controller)) return null
        setErrorMessage(toApiError(cause).message ?? NETWORK_FALLBACK)
        setStep('preview')
        return null
      }
    },
    [examId, preview, isCurrent, startRequest]
  )

  const reset = useCallback((): void => {
    requestRef.current?.abort()
    requestRef.current = null
    setStep('idle')
    setFile(null)
    setPreview(null)
    setResult(null)
    setErrorMessage(null)
  }, [])

  return {
    step,
    file,
    preview,
    result,
    errorMessage,
    startUpload,
    retry,
    applyToPending,
    reset,
  }
}

function toApiError(cause: unknown): ApiError {
  return cause instanceof ApiError ? cause : new ApiError(0, NETWORK_FALLBACK)
}
