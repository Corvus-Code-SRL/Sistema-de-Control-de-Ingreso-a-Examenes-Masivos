import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { listRectificationRequests } from '../services/rectificationRequestsService'
import type { RectificationInbox } from '../types/riskRectification.types'
import { notifyRectificationRequestsChanged } from './usePendingRectificationRequestsCount'

/** Bandeja de solicitudes del docente. `reload` también avisa al contador del menú. */
export function useRectificationInbox() {
  const resource = useAsyncResource<RectificationInbox>((signal) => listRectificationRequests(signal), [])
  const { reload } = resource

  const reloadAll = useCallback(() => {
    reload()
    notifyRectificationRequestsChanged()
  }, [reload])

  return {
    inbox: resource.data,
    status: resource.status,
    error: resource.error,
    reload,
    reloadAll,
  }
}
