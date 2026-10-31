import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import {
  confirmStudentRoster,
  previewStudentRoster,
  validateRosterFile,
} from '../services/rosterService'
import type { RosterConfirmationData, RosterPreviewData } from '../types/roster.types'

export type RosterUploadStep =
  | 'file'
  | 'processing'
  | 'preview'
  | 'confirm'
  | 'confirming'
  | 'result'

export interface UseRosterUploadResult {
  step: RosterUploadStep
  /** Archivo seleccionado; permanece disponible hasta cancelar o confirmar. */
  file: File | null
  preview: RosterPreviewData | null
  result: RosterConfirmationData | null
  errorMessage: string | null
  /** Un fallo de red se puede repetir con el mismo archivo. */
  canRetry: boolean
  selectFile: (file: File) => void
  submitPreview: () => void
  retry: () => void
  goToConfirm: () => void
  backToPreview: () => void
  confirm: () => void
  reset: () => void
}

const NETWORK_FALLBACK = 'No se pudo conectar con el servidor. Revise su conexión.'
const RELOAD_FILE = 'Vuelva a cargar el archivo.'
const ROSTER_UNTOUCHED = 'La nómina del grupo no se modificó.'

/**
 * Controla el flujo de carga de nómina: selección, preview, confirmación y resultado.
 *
 * Seleccionar un archivo no lo envía automáticamente. La petición de preview se
 * ejecuta solo cuando el docente pulsa «Previsualizar».
 *
 * Cancelar descarta el estado local y aborta cualquier petición en curso.
 */
export function useRosterUpload(groupId: number): UseRosterUploadResult {
  const [step, setStep] = useState<RosterUploadStep>('file')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<RosterPreviewData | null>(null)
  const [result, setResult] = useState<RosterConfirmationData | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [canRetry, setCanRetry] = useState(false)

  const requestRef = useRef<AbortController | null>(null)
  const confirmingRef = useRef(false)
  const mountedRef = useRef(true)

  useEffect(() => {
    mountedRef.current = true

    return () => {
      mountedRef.current = false
      requestRef.current?.abort()
    }
  }, [])

  /** Cancela la petición anterior y crea el controlador de la siguiente. */
  const startRequest = useCallback((): AbortController => {
    requestRef.current?.abort()

    const controller = new AbortController()
    requestRef.current = controller

    return controller
  }, [])

  /** Una respuesta solo puede actualizar el estado si sigue siendo la vigente. */
  const isCurrent = useCallback(
    (controller: AbortController): boolean =>
      mountedRef.current && requestRef.current === controller,
    []
  )

  const runPreview = useCallback(
    async (selectedFile: File): Promise<void> => {
      const controller = startRequest()

      setErrorMessage(null)
      setCanRetry(false)
      setStep('processing')

      try {
        const response = await previewStudentRoster(
          groupId,
          selectedFile,
          controller.signal
        )

        if (!isCurrent(controller)) return

        setPreview(response.data)
        setStep('preview')
      } catch (cause) {
        if (!isCurrent(controller)) return

        const error = toApiError(cause)

        setPreview(null)
        setErrorMessage(previewErrorMessage(error))
        setCanRetry(error.status === 0)
        setStep('file')
      }
    },
    [groupId, isCurrent, startRequest]
  )

  const runConfirm = useCallback(
    async (token: string): Promise<void> => {
      const controller = startRequest()

      setErrorMessage(null)
      setCanRetry(false)
      setStep('confirming')

      try {
        const response = await confirmStudentRoster(
          groupId,
          token,
          controller.signal
        )

        if (!isCurrent(controller)) return

        setResult(response.data)
        setPreview(null)
        setFile(null)
        setStep('result')
      } catch (cause) {
        if (!isCurrent(controller)) return

        setPreview(null)
        setFile(null)
        setErrorMessage(confirmErrorMessage(toApiError(cause)))
        setCanRetry(false)
        setStep('file')
      } finally {
        confirmingRef.current = false
      }
    },
    [groupId, isCurrent, startRequest]
  )

  const selectFile = useCallback(
    (selectedFile: File): void => {
      requestRef.current?.abort()
      requestRef.current = null
      confirmingRef.current = false

      const rejection = validateRosterFile(selectedFile)

      if (rejection !== null) {
        setFile(null)
        setPreview(null)
        setResult(null)
        setErrorMessage(rejection)
        setCanRetry(false)
        setStep('file')

        return
      }

      setFile(selectedFile)
      setPreview(null)
      setResult(null)
      setErrorMessage(null)
      setCanRetry(false)
      setStep('file')
    },
    []
  )

  const submitPreview = useCallback((): void => {
    if (file === null) return

    void runPreview(file)
  }, [file, runPreview])

  const retry = useCallback((): void => {
    if (file === null) return

    void runPreview(file)
  }, [file, runPreview])

  const goToConfirm = useCallback((): void => {
    if (preview === null) return

    setErrorMessage(null)
    setStep('confirm')
  }, [preview])

  const backToPreview = useCallback((): void => {
    if (preview === null) return

    setErrorMessage(null)
    setStep('preview')
  }, [preview])

  const confirm = useCallback((): void => {
    if (preview === null || confirmingRef.current) return

    confirmingRef.current = true
    void runConfirm(preview.token)
  }, [preview, runConfirm])

  const reset = useCallback((): void => {
    requestRef.current?.abort()
    requestRef.current = null
    confirmingRef.current = false

    setStep('file')
    setFile(null)
    setPreview(null)
    setResult(null)
    setErrorMessage(null)
    setCanRetry(false)
  }, [])

  return {
    step,
    file,
    preview,
    result,
    errorMessage,
    canRetry,
    selectFile,
    submitPreview,
    retry,
    goToConfirm,
    backToPreview,
    confirm,
    reset,
  }
}

function toApiError(cause: unknown): ApiError {
  return cause instanceof ApiError ? cause : new ApiError(0, NETWORK_FALLBACK)
}

/** Mensajes del procesamiento del archivo; todavía no se escribió nada. */
function previewErrorMessage(error: ApiError): string {
  if (error.isNotFound) {
    return 'El grupo no existe o fue dado de baja.'
  }

  if (error.isValidation) {
    const rejected: string | undefined = error.errors.archivo?.[0]

    return rejected ?? `${error.message} ${ROSTER_UNTOUCHED}`
  }

  return error.message
}

/**
 * Mensajes de la confirmación.
 *
 * Cualquier error después de enviar el token obliga a volver a cargar el archivo,
 * porque el token puede haber quedado invalidado o consumido.
 */
function confirmErrorMessage(error: ApiError): string {
  if (error.isNotFound) {
    return `La previsualización expiró o ya fue confirmada. ${RELOAD_FILE}`
  }

  if (error.isForbidden) {
    return `La previsualización no pertenece al docente actual. ${RELOAD_FILE}`
  }

  if (error.isValidation) {
    const rejected: string | undefined = error.errors.token?.[0]

    return `${rejected ?? error.message} ${RELOAD_FILE}`
  }

  if (error.status === 0) {
    return `${error.message} ${RELOAD_FILE}`
  }

  return `${error.message} ${RELOAD_FILE}`
}