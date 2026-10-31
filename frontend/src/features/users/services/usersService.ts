import { apiClient, apiPost } from '@/lib/api-client'
import type {
  AccountFilters,
  ActiveAssignments,
  NewUserAccount,
  Role,
  SisPerson,
  UserAccount,
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

export const ACCOUNTS_PER_PAGE = 10

/** Una página de cuentas: la búsqueda (sin tildes), el rol y la paginación los resuelve el servidor. */
export async function getUserAccounts(
  { q, rol, page }: AccountFilters,
  signal?: AbortSignal
): Promise<UserAccounts> {
  const response = await apiClient<UserAccountsResponse>('/usuarios', {
    query: {
      q: q === '' ? undefined : q,
      rol: rol ?? undefined,
      page,
      per_page: ACCOUNTS_PER_PAGE,
    },
    signal,
  })

  return {
    accounts: response.data,
    currentUserId: response.meta.id_usuario_actual,
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: response.meta.last_page,
  }
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

/**
 * Consulta un código SIS para precargar los nombres del formulario. Devuelve `null` si el SIS no lo
 * conoce: eso no impide el registro. Los rechazos llegan como ApiError: 503 si el SIS no responde
 * (tampoco bloquea) y 422 con `errors.cod_sis` si ya hay una cuenta con ese código.
 */
export async function verifySisCode(codSis: string, signal?: AbortSignal): Promise<SisPerson | null> {
  const response = await apiClient<{ data: SisPerson | null }>(
    `/sis/verificar/${encodeURIComponent(codSis)}`,
    { signal }
  )

  return response.data
}

/** Código INV libre para un docente invitado; el registro lo vuelve a comprobar y puede proponer otro (409). */
export async function getGuestCode(signal?: AbortSignal): Promise<string> {
  const response = await apiClient<{ data: { cod_sis: string } }>('/usuarios/codigo-invitado', { signal })

  return response.data.cod_sis
}

/** Crea la cuenta y, si se eligió, le asigna el rol en una sola operación del servidor. */
export async function registerUser(account: NewUserAccount): Promise<UserAccount> {
  const response = await apiPost<{ data: UserAccount }>('/usuarios', account)

  return response.data
}

export async function assignRole(userId: string, roleId: number): Promise<Role> {
  const response = await apiPost<{ data: Role }>(`/usuarios/${userId}/rol`, { id_rol: roleId })

  return response.data
}
