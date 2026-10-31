import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { ApiError } from '@/lib/api-client'
import { getGroup } from '../services/groupsService'
import type { GroupDetail } from '../types/group.types'

export interface UseGroupDetailResult {
  detail: GroupDetail | null
  isLoading: boolean
  /** El grupo existe pero lo dicta otro docente: no debe mostrarse su contenido. */
  isForbidden: boolean
  isNotFound: boolean
  error: ApiError | null
  reload: () => void
}

/**
 * Detalle de un grupo, con el permiso ya resuelto.
 *
 * Quien decide es el backend: responde 403 cuando el grupo es de otro docente.
 * `es_mio` se sigue revisando como respaldo, de modo que la vista tiene un
 * único estado de «no es suyo» venga de donde venga.
 */
export function useGroupDetail(groupId: number): UseGroupDetailResult {
  const resource = useAsyncResource<GroupDetail>(
    useCallback((signal) => getGroup(groupId, signal), [groupId]),
    [groupId]
  )

  const deniedByResource = resource.data !== null && !resource.data.group.es_mio
  const deniedByStatus = resource.error?.isForbidden ?? false

  return {
    detail: resource.data,
    isLoading: resource.status === 'loading',
    isForbidden: deniedByStatus || (resource.status === 'success' && deniedByResource),
    isNotFound: resource.error?.isNotFound ?? false,
    // Un 403 no es un fallo que reintentar: la vista lo explica por su cuenta.
    error: resource.error?.isForbidden ? null : resource.error,
    reload: resource.reload,
  }
}
