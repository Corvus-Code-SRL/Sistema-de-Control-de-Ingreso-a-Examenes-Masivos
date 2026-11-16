import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { searchRectifiableStudents } from '../services/riskRectificationService'
import type { StudentSearchResult } from '../types/riskRectification.types'

export type StudentSearchStatus = 'idle' | 'loading' | 'success' | 'error'

/**
 * Búsqueda de estudiantes a pedido: solo consulta cuando la persona envía el dato, no al teclear.
 * Una búsqueda nueva cancela la anterior; `result` es la de la última que terminó.
 */
export function useRectifiableStudentSearch() {
  const [status, setStatus] = useState<StudentSearchStatus>('idle')
  const [result, setResult] = useState<StudentSearchResult | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const controller = useRef<AbortController | null>(null)

  useEffect(() => () => controller.current?.abort(), [])

  const search = useCallback(async (term: string): Promise<StudentSearchResult | null> => {
    controller.current?.abort()
    const current = new AbortController()
    controller.current = current

    setStatus('loading')
    setError(null)

    try {
      const found = await searchRectifiableStudents(term, current.signal)

      if (current.signal.aborted) return null

      setResult(found)
      setStatus('success')

      return found
    } catch (cause) {
      if (current.signal.aborted) return null

      setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))
      setStatus('error')

      return null
    }
  }, [])

  const reset = useCallback(() => {
    controller.current?.abort()
    setStatus('idle')
    setResult(null)
    setError(null)
  }, [])

  return { status, result, error, search, reset }
}
