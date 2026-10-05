import type { LoginResponse, MeResponse, RoleName, SessionUser } from '@/features/auth/types/auth.types'

/**
 * Cuentas sembradas por AccountTestDataSeeder (contraseña «password») y las respuestas reales
 * de la API de autenticación (docs/api/autenticacion.md).
 */

export const TOKEN_STORAGE_KEY = 'sciem.auth.token'

interface Account {
  cod_sis: string
  user: SessionUser
  role: { id_rol: number; nombre_rol: RoleName }
}

function account(
  cod_sis: string,
  id_usuario: string,
  nombre: string,
  paterno: string,
  materno: string,
  id_rol: number,
  nombre_rol: RoleName
): Account {
  return {
    cod_sis,
    user: {
      id_usuario,
      cod_sis,
      nombre,
      apellido_paterno: paterno,
      apellido_materno: materno,
      nombre_completo: `${nombre} ${paterno} ${materno}`,
      correo: `${nombre.toLowerCase()}.${paterno.toLowerCase()}@sciem.test`,
      estado: 'ACTIVO',
    },
    role: { id_rol, nombre_rol },
  }
}

export const accounts = {
  administrador: account('ADM0001', '00000000-0000-4000-8000-000000000001', 'Valeria', 'Montaño', 'Ríos', 1, 'Administrador'),
  docente: account('10452', '00000000-0000-4000-8000-000000000011', 'Marcelo', 'Quiroga', 'Andrade', 2, 'Docente'),
  auxiliar: account('201800451', '00000000-0000-4000-8000-000000000021', 'Daniela', 'Ferrufino', 'Soliz', 3, 'Auxiliar'),
}

export type AccountKey = keyof typeof accounts

export function loginBody(key: AccountKey, token = `1|token-${key}`): LoginResponse {
  const { user, role } = accounts[key]

  return {
    data: {
      usuario: user,
      rol: role,
      token,
      tipo_token: 'Bearer',
      expira_en: '2026-10-03T09:00:00+00:00',
    },
    mensaje: 'Sesión iniciada correctamente.',
  }
}

export function meBody(key: AccountKey): MeResponse {
  const { user, role } = accounts[key]

  return {
    data: {
      usuario: user,
      rol: role,
      navegacion: { permisos: [], interfaces: [] },
      password_confirmado_en: '2026-10-02T21:00:00+00:00',
    },
  }
}

/** Cuerpos de error de los rechazos de login. */
export const loginErrors = {
  credentials: {
    status: 401,
    body: { message: 'Código SIS o contraseña incorrectos.', motivo: 'credenciales_invalidas' },
  },
  inactive: {
    status: 403,
    body: { message: 'Su cuenta está deshabilitada. Contacte al Administrador.', motivo: 'cuenta_inactiva' },
  },
  noRole: {
    status: 403,
    body: { message: 'Su cuenta no tiene un rol vigente. Contacte al Administrador.', motivo: 'sin_rol_vigente' },
  },
  throttled: {
    status: 429,
    body: { message: 'Too Many Attempts.' },
  },
}
