import type {
  ActiveAssignments,
  Role,
  RoleHistoryEntry,
  SisPerson,
  UserAccount,
  UserAccountDetail,
} from '@/features/users'

/**
 * Datos de prueba de cuentas y roles.
 *
 * Los ids son uuid, como en la base: las rutas del backend rechazan cualquier otro valor.
 */

export const ADMIN_ID = '00000000-0000-4000-8000-000000000001'

export const roles: Role[] = [
  { id_rol: 1, nombre_rol: 'Administrador', descripcion: 'Gestión global del sistema.', estado: 'ACTIVO' },
  { id_rol: 2, nombre_rol: 'Docente', descripcion: 'Gestión de exámenes y grupos.', estado: 'ACTIVO' },
  { id_rol: 3, nombre_rol: 'Auxiliar', descripcion: 'Apoyo en el control de ingreso.', estado: 'ACTIVO' },
]

export function makeAccount(overrides: Partial<UserAccount> = {}): UserAccount {
  return {
    id_usuario: '3f2b6c1e-8d4a-4c7e-9a51-2b7d0e6f4a10',
    cod_sis: '201600845',
    nombre: 'Laura',
    apellido_paterno: 'Mendoza',
    apellido_materno: 'Rivas',
    nombre_completo: 'Laura Mendoza Rivas',
    correo: 'l.mendoza@umss.edu',
    estado: 'ACTIVO',
    rol: null,
    ...overrides,
  }
}

export const administrator = makeAccount({
  id_usuario: ADMIN_ID,
  cod_sis: '000000000',
  nombre: 'Usuario',
  apellido_paterno: 'De Prueba',
  apellido_materno: null,
  nombre_completo: 'Usuario De Prueba',
  correo: 'usuario.prueba@sciem.local',
  rol: { id_rol: 1, nombre_rol: 'Administrador' },
})

export const teacher = makeAccount({
  id_usuario: '9c1d2e3f-4a5b-4c6d-8e7f-0a1b2c3d4e5f',
  cod_sis: '199800412',
  nombre: 'Pablo',
  apellido_paterno: 'Careaga',
  apellido_materno: 'Rojas',
  nombre_completo: 'Pablo Careaga Rojas',
  correo: 'p.careaga@umss.edu',
  rol: { id_rol: 2, nombre_rol: 'Docente' },
})

export const assistant = makeAccount({
  id_usuario: 'b7e8f9a0-1b2c-4d3e-9f4a-5b6c7d8e9f01',
  cod_sis: '202103377',
  nombre: 'Mateo',
  apellido_paterno: 'Quiroga',
  apellido_materno: 'Salinas',
  nombre_completo: 'Mateo Quiroga Salinas',
  correo: '202103377@est.umss.edu',
  rol: { id_rol: 3, nombre_rol: 'Auxiliar' },
})

export function accountsResponse(accounts: UserAccount[], currentUserId: string | null = ADMIN_ID) {
  return { data: accounts, meta: { id_usuario_actual: currentUserId } }
}

export function makeDetail(
  account: UserAccount,
  history: RoleHistoryEntry[] = []
): UserAccountDetail {
  return { ...account, historial_roles: history }
}

export function detailResponse(detail: UserAccountDetail, currentUserId: string | null = ADMIN_ID) {
  return { data: detail, meta: { id_usuario_actual: currentUserId } }
}

export function assignmentsResponse(overrides: Partial<ActiveAssignments> = {}) {
  return {
    data: { grupos: 0, materias: 0, examenes: 0, tiene_activas: false, ...overrides },
  }
}

/** Persona que devuelve la verificación en el SIS (la misma que simula el backend). */
export const sisPerson: SisPerson = {
  nombre: 'Laura',
  paterno: 'Mendoza',
  materno: 'Rivas',
  tipo: 'Docente',
  facultad: 'Facultad de Ciencias y Tecnología',
}

export const userMatchers = {
  sisVerification: (url: string) => url.includes('/sis/verificar/'),
  roles: (url: string) => url.endsWith('/roles'),
  accounts: (url: string) => url.endsWith('/usuarios'),
  account: (url: string) => /\/usuarios\/[0-9a-f-]{36}$/.test(url),
  assignments: (url: string) => url.endsWith('/asignaciones'),
  assignRole: (url: string) => url.endsWith('/rol'),
}
