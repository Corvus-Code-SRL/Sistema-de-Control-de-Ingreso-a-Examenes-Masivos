import { apiClient, apiPost } from '@/lib/api-client'
import type {
  ActiveAssignments,
  Role,
  UserAccountDetailResponse,
  UserAccounts,
  UserAccountsResponse,
  UserAccountWithContext,
} from '../types/users.types'

/**
 * Cuentas y roles del módulo Security.
 *
 * Todas las operaciones son del Administrador: el backend responde 403 a
 * cualquier otra cuenta.
 */

export async function getUserAccounts(signal?: AbortSignal): Promise<UserAccounts> {
  const response = await apiClient<UserAccountsResponse>('/usuarios', { signal })

  return { accounts: response.data, currentUserId: response.meta.id_usuario_actual }
}

export async function getUserAccount(
  userId: string,
  signal?: AbortSignal
): Promise<UserAccountWithContext> {
  const response = await apiClient<UserAccountDetailResponse>(`/usuarios/${userId}`, { signal })

  return { account: response.data, currentUserId: response.meta.id_usuario_actual }
}

export async function getRoles(signal?: AbortSignal): Promise<Role[]> {
  const response = await apiClient<{ data: Role[] }>('/roles', { signal })

  return response.data
}

export async function getActiveAssignments(
  userId: string,
  signal?: AbortSignal
): Promise<ActiveAssignments> {
  const response = await apiClient<{ data: ActiveAssignments }>(
    `/usuarios/${userId}/asignaciones`,
    { signal }
  )

  return response.data
}

export async function assignRole(userId: string, roleId: number): Promise<Role> {
  const response = await apiPost<{ data: Role }>(`/usuarios/${userId}/rol`, { id_rol: roleId })

  return response.data
}
