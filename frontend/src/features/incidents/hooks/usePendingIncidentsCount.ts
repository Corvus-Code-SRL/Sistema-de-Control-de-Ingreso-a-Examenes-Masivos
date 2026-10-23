import { useAsyncResource } from '@/hooks/useAsyncResource'
import { listIncidents } from '../services/incidentsService'

/**
 * Cantidad de incidencias pendientes del docente; 0 si no aplica, aún carga o falla.
 *
 * `enabled` evita la petición cuando el área no es Docente (Auxiliar, Administrador).
 */
export function usePendingIncidentsCount(enabled: boolean): number {
  const resource = useAsyncResource(
    (signal) =>
      enabled
        ? listIncidents('pendiente', undefined, 'asc', signal)
        : Promise.resolve({ data: [], meta: { total_pendientes: 0, total_revisadas: 0 } }),
    [enabled]
  )

  return resource.data?.meta.total_pendientes ?? 0
}
