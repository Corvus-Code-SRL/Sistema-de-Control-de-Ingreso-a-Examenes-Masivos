import { apiClient, apiPost } from '@/lib/api-client'
import type {
  ChangePasswordInput,
  ChangePasswordResponse,
  ConfirmPasswordResponse,
  LoginResponse,
  LogoutResponse,
  MeResponse,
} from '../types/auth.types'

/**
 * Los endpoints de autenticación (docs/api/autenticacion.md).
 *
 * Todos pasan por `api-client`. Cada uno declara cómo trata un 401:
 * - login: un 401 son credenciales incorrectas, no una sesión caída (`skipAuth`).
 * - yo y logout: el 401 lo resuelve quien llama, sin bloquear la pantalla (`ignoreUnauthorized`).
 * - confirmar-password y cambiar-contrasenia: un 401 sí es una sesión caída, como en el resto de la API.
 */

export function login(codSis: string, password: string, signal?: AbortSignal): Promise<LoginResponse> {
  return apiPost<LoginResponse>(
    '/auth/login',
    { cod_sis: codSis, password },
    { skipAuth: true, signal }
  )
}

/** Cuenta, rol vigente y navegación de la sesión actual. */
export function getSession(signal?: AbortSignal): Promise<MeResponse> {
  return apiClient<MeResponse>('/auth/yo', { ignoreUnauthorized: true, signal })
}

/** Revoca solo el token en uso. */
export function logout(): Promise<LogoutResponse> {
  return apiPost<LogoutResponse>('/auth/logout', {}, { ignoreUnauthorized: true })
}

export function confirmPassword(password: string): Promise<ConfirmPasswordResponse> {
  return apiPost<ConfirmPasswordResponse>('/auth/confirmar-password', { password })
}

/**
 * Cierra la contraseña temporal. Un 422 son los campos rechazados (actual incorrecta, nueva corta,
 * sin confirmar o igual a la actual): no es una sesión caída.
 */
export function changePassword(input: ChangePasswordInput): Promise<ChangePasswordResponse> {
  return apiPost<ChangePasswordResponse>('/auth/cambiar-contrasenia', input)
}
