import type { Area, RoleName } from './types/auth.types'

/**
 * Capacidades de la interfaz: lo que un rol permite hacer en pantalla.
 *
 * Las rutas piden una capacidad, nunca un nombre de rol. El Auxiliar es una versión liviana del
 * Docente, así que el Docente incluye las capacidades operativas del Auxiliar (ingreso e
 * incidencias) y las suyas: se define una vez aquí y ninguna ruta tiene que listar los dos roles.
 * Las capacidades propias de la auxiliatura (`asignaciones.consultar`) quedan fuera de esa
 * herencia: el Docente no consulta exámenes como auxiliar, y el menú lateral las usa para no
 * mostrarle las secciones del Auxiliar.
 *
 * Por ahora este mapa vive en el cliente. A futuro estas capacidades deberían derivarse de los
 * permisos que informa el backend (`navegacion.permisos` de GET /auth/yo).
 *
 * Esto NO decide si un docente puede actuar sobre UN examen concreto (ser su dueño, estar
 * habilitado como auxiliar o haber aceptado una invitación): esa es una política del backend.
 */

/** Lo que el Auxiliar y el Docente comparten: operar el ingreso y registrar incidencias. */
const SHARED_OPERATION_CAPABILITIES = ['ingreso.operar', 'incidencias.registrar', 'situaciones.registrar'] as const

/** Consultar los grupos y exámenes donde se fue habilitado como auxiliar; solo del Auxiliar. */
const AUXILIAR_ONLY_CAPABILITIES = ['asignaciones.consultar'] as const

const AUXILIAR_CAPABILITIES = [
  ...SHARED_OPERATION_CAPABILITIES,
  ...AUXILIAR_ONLY_CAPABILITIES,
] as const

/** La Central de Riesgo es institucional y de solo lectura: la consultan el Docente y el Administrador, no el Auxiliar. */
const RISK_CENTER_CAPABILITIES = ['riesgo.consultar'] as const

const DOCENTE_CAPABILITIES = [
  ...SHARED_OPERATION_CAPABILITIES,
  'examenes.gestionar',
  'grupos.gestionar',
  'auxiliares.gestionar',
  ...RISK_CENTER_CAPABILITIES,
] as const

const ADMINISTRADOR_CAPABILITIES = ['administracion.gestionar', ...RISK_CENTER_CAPABILITIES] as const

export type Capability =
  | (typeof AUXILIAR_CAPABILITIES)[number]
  | (typeof DOCENTE_CAPABILITIES)[number]
  | (typeof ADMINISTRADOR_CAPABILITIES)[number]

const CAPABILITIES_BY_ROLE: Record<RoleName, readonly Capability[]> = {
  Auxiliar: AUXILIAR_CAPABILITIES,
  Docente: DOCENTE_CAPABILITIES,
  Administrador: ADMINISTRADOR_CAPABILITIES,
}

function isRoleName(name: string): name is RoleName {
  return name in CAPABILITIES_BY_ROLE
}

/** Un rol desconocido no concede nada. */
export function capabilitiesOf(roleName: string | null | undefined): readonly Capability[] {
  return roleName && isRoleName(roleName) ? CAPABILITIES_BY_ROLE[roleName] : []
}

export function hasCapability(roleName: string | null | undefined, capability: Capability): boolean {
  return capabilitiesOf(roleName).includes(capability)
}

/** El área de trabajo (navegación y rutas) que corresponde a un rol. */
export function areaForRole(roleName: string | null | undefined): Area {
  if (roleName === 'Administrador') return 'administrador'
  if (roleName === 'Auxiliar') return 'auxiliar'

  return 'docente'
}