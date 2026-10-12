import { ApiError } from '@/lib/api-client'

/** Mensaje para el Administrador cuando el servidor rechaza la asignación del rol. */
export function describeRoleError(cause: unknown): string {
  if (cause instanceof ApiError) {
    return cause.errors.id_rol?.[0] ?? cause.message
  }

  return 'No se pudo guardar el rol. Intente nuevamente.'
}
