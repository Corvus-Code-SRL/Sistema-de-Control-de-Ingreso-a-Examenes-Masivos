import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getStudentRecords } from '../services/riskRectificationService'
import type { StudentRecords } from '../types/riskRectification.types'

/**
 * Registros confirmados de un estudiante. `records` es null mientras lo cargado no sea de este estudiante:
 * el hook conserva la respuesta anterior mientras llega la nueva, y mostrarla sería enseñar otra persona.
 */
export function useStudentRecords(studentId: number) {
  const resource = useAsyncResource<StudentRecords>(
    useCallback((signal) => getStudentRecords(studentId, signal), [studentId]),
    [studentId]
  )

  const isCurrent = resource.data?.estudiante.id_estudiante === studentId

  return {
    records: isCurrent ? resource.data : null,
    status: resource.status === 'success' && !isCurrent ? ('loading' as const) : resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
