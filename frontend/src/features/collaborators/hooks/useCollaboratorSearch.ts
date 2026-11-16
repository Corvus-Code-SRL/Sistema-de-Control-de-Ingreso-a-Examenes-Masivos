import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { collaboratorsService } from '../services/collaboratorsService'
import type { CollaboratorCandidate } from '../types/collaborator.types'

export type CollaboratorSearchStatus = 'idle' | 'loading' | 'success' | 'error'

export interface CollaboratorSearch {
  status: CollaboratorSearchStatus
  /** Lo último que se buscó; vacío mientras no se haya buscado nada. */
  criteria: string
  results: CollaboratorCandidate[]
  error: ApiError | null
  search: (criteria: string) => void
  /** Marca como ya invitado a un resultado, sin volver a buscar. */
  markInvited: (teacherId: string) => void
}

/**
 * Búsqueda de docentes invitables al examen elegido. Cambiar de examen la reinicia: los resultados
 * de una materia no valen para otra. Una búsqueda nueva cancela la anterior que siguiera en curso.
 */
export function useCollaboratorSearch(examId: number | null): CollaboratorSearch {
  const [status, setStatus] = useState<CollaboratorSearchStatus>('idle')
  const [criteria, setCriteria] = useState('')
  const [results, setResults] = useState<CollaboratorCandidate[]>([])
  const [error, setError] = useState<ApiError | null>(null)
  const controller = useRef<AbortController | null>(null)

  useEffect(() => {
    controller.current?.abort()
    setStatus('idle')
    setCriteria('')
    setResults([])
    setError(null)

    return () => controller.current?.abort()
  }, [examId])

  const search = useCallback(
    (term: string) => {
      const trimmed = term.trim()
      if (examId === null || trimmed === '') return

      controller.current?.abort()
      const current = new AbortController()
      controller.current = current

      setCriteria(trimmed)
      setStatus('loading')
      setError(null)

      collaboratorsService
        .searchTeachers(examId, trimmed, current.signal)
        .then((found) => {
          if (current.signal.aborted) return
          setResults(found)
          setStatus('success')
        })
        .catch((cause: unknown) => {
          if (current.signal.aborted) return
          setResults([])
          setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))
          setStatus('error')
        })
    },
    [examId]
  )

  const markInvited = useCallback((teacherId: string) => {
    setResults((previous) =>
      previous.map((candidate) => (candidate.id_usuario === teacherId ? { ...candidate, ya_invitado: true } : candidate))
    )
  }, [])

  return { status, criteria, results, error, search, markInvited }
}
