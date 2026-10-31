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
  { id_rol: 4, nombre_rol: 'Invitado', descripcion: 'Docente sin código SIS.', estado: 'ACTIVO' },
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
    es_invitado: false,
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

/** Docente invitado: su código INV lo generó el sistema y solo puede tener el rol Invitado. */
export const guestTeacher = makeAccount({
  id_usuario: 'd4e5f6a7-b8c9-4d0e-8f1a-2b3c4d5e6f70',
  cod_sis: 'INV4821',
  nombre: 'Rosa',
  apellido_paterno: 'Aguilar',
  apellido_materno: null,
  nombre_completo: 'Rosa Aguilar',
  correo: 'r.aguilar@umss.edu',
  es_invitado: true,
  rol: { id_rol: 4, nombre_rol: 'Invitado' },
})

interface PageOptions {
  currentUserId?: string | null
  page?: number
  perPage?: number
  total?: number
  lastPage?: number
}

/** Respuesta paginada de `GET /usuarios`; sin opciones, todas las cuentas caben en una página. */
export function accountsResponse(accounts: UserAccount[], options: PageOptions | string | null = {}) {
  const {
    currentUserId = ADMIN_ID,
    page = 1,
    perPage = 10,
    total = accounts.length,
    lastPage = 1,
  } = typeof options === 'object' && options !== null ? options : { currentUserId: options }

  return {
    data: accounts,
    meta: {
      id_usuario_actual: currentUserId,
      current_page: page,
      per_page: perPage,
      total,
      last_page: lastPage,
    },
  }
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
  guestCode: (url: string) => url.endsWith('/usuarios/codigo-invitado'),
  accounts: (url: string) => new URL(url).pathname.endsWith('/usuarios'),
  account: (url: string) => /\/usuarios\/[0-9a-f-]{36}$/.test(url),
  assignments: (url: string) => url.endsWith('/asignaciones'),
  assignRole: (url: string) => url.endsWith('/rol'),
}
