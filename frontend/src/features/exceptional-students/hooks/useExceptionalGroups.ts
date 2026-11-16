import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { exceptionalStudentService } from '../services/exceptionalStudentService'
import type { ExceptionalGroup } from '../types/exceptional-student.types'

/** Grupos del docente en el período vigente, para elegir dónde registrar al estudiante. */
export function useExceptionalGroups() {
  return useAsyncResource<ExceptionalGroup[]>(
    useCallback(async (signal) => (await exceptionalStudentService.groups(signal)).data, []),
    []
  )
}
