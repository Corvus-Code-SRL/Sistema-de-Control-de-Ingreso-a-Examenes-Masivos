import type { Area, RoleName } from './types/auth.types'

/**
 * Capacidades de la interfaz: lo que un rol permite hacer en pantalla.
 *
 * Las rutas piden una capacidad, nunca un nombre de rol. El Auxiliar es una versión liviana del
 * Docente, así que el Docente incluye todas las del Auxiliar y las suyas: se define una vez
 * aquí y ninguna ruta tiene que listar los dos roles.
 *
 * Esto NO decide si un docente puede actuar sobre UN examen concreto (ser su dueño, estar
 * habilitado como auxiliar o haber aceptado una invitación): esa es una política del backend.
 */

const AUXILIAR_CAPABILITIES = ['ingreso.operar', 'incidencias.registrar'] as const

const DOCENTE_CAPABILITIES = [
  ...AUXILIAR_CAPABILITIES,
  'examenes.gestionar',
  'grupos.gestionar',
  'auxiliares.gestionar',
] as const

const ADMINISTRADOR_CAPABILITIES = ['administracion.gestionar'] as const

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
  return roleName === 'Administrador' ? 'administrador' : 'docente'
}
