import type { Area, RoleName } from './types/auth.types'

/**
 * Capacidades de la interfaz: lo que un rol permite hacer en pantalla.
 *
 * Las rutas y el menú piden una capacidad, nunca un nombre de rol. Las capacidades del Docente, del
 * Invitado y de la Central de Riesgo salen de `ACCESS_BY_FEATURE`, que es el espejo de
 * `config('sciem.access')` del backend (backend/config/sciem.php). Ambos mapas DEBEN mantenerse
 * iguales: capabilities.access.test.ts falla si difieren. Cambiar el acceso de un rol es editar su
 * lista en los dos lugares.
 *
 * El Auxiliar y el Administrador conservan además sus capacidades propias (`ROLE_OWN_CAPABILITIES`).
 * El Auxiliar es una versión liviana del Docente: sus capacidades operativas (ingreso e incidencias)
 * las comparten los roles que ejercen la docencia. Las propias de la auxiliatura
 * (`asignaciones.consultar`) quedan fuera de esa herencia.
 *
 * Esto NO decide si un docente puede actuar sobre UN examen concreto (ser su dueño, estar
 * habilitado como auxiliar o haber aceptado una invitación): esa es una política del backend.
 */

/** Funciones con acceso configurable por rol; los mismos nombres que `sciem.access` del backend. */
export type AccessFeature =
  | 'teaching'
  | 'exam_assistants'
  | 'incidents'
  | 'risk_center'
  | 'group_assistants'
  | 'report_assistant'
  | 'special_cases'
  | 'invite_teachers'

/**
 * Qué roles ejercen cada función. El Invitado (docente sin código SIS) ejerce la docencia, habilita
 * auxiliares por examen y revisa incidencias; no consulta la Central de Riesgo, no gestiona
 * auxiliares de grupo, no reporta auxiliares, no acepta casos especiales y, hasta que producto lo
 * decida, no invita a otros docentes.
 */
export const ACCESS_BY_FEATURE: Record<AccessFeature, readonly RoleName[]> = {
  teaching: ['Docente', 'Invitado'],
  exam_assistants: ['Docente', 'Invitado'],
  incidents: ['Docente', 'Invitado'],
  risk_center: ['Administrador', 'Docente'],
  group_assistants: ['Docente'],
  report_assistant: ['Docente'],
  special_cases: ['Docente'],
  invite_teachers: ['Docente'],
}

const CAPABILITIES_BY_FEATURE = {
  teaching: ['grupos.gestionar', 'examenes.gestionar', 'ingreso.operar', 'incidencias.registrar'],
  exam_assistants: ['auxiliares.examen'],
  incidents: ['incidencias.revisar'],
  risk_center: ['riesgo.consultar'],
  group_assistants: ['auxiliares.gestionar'],
  report_assistant: ['auxiliares.reportar'],
  special_cases: ['casos-especiales.gestionar'],
  invite_teachers: ['docentes.invitar'],
} as const

const ROLE_OWN_CAPABILITIES = {
  Auxiliar: ['ingreso.operar', 'incidencias.registrar', 'asignaciones.consultar'],
  Administrador: ['administracion.gestionar'],
  Docente: [],
  Invitado: [],
} as const

export type Capability =
  | (typeof CAPABILITIES_BY_FEATURE)[AccessFeature][number]
  | (typeof ROLE_OWN_CAPABILITIES)[RoleName][number]

const ROLE_NAMES = Object.keys(ROLE_OWN_CAPABILITIES) as RoleName[]

function buildCapabilities(role: RoleName): readonly Capability[] {
  const fromFeatures = (Object.keys(ACCESS_BY_FEATURE) as AccessFeature[])
    .filter((feature) => ACCESS_BY_FEATURE[feature].includes(role))
    .flatMap((feature) => CAPABILITIES_BY_FEATURE[feature])

  return [...new Set<Capability>([...ROLE_OWN_CAPABILITIES[role], ...fromFeatures])]
}

const CAPABILITIES_BY_ROLE: Record<RoleName, readonly Capability[]> = {
  Administrador: buildCapabilities('Administrador'),
  Docente: buildCapabilities('Docente'),
  Auxiliar: buildCapabilities('Auxiliar'),
  Invitado: buildCapabilities('Invitado'),
}

function isRoleName(name: string): name is RoleName {
  return ROLE_NAMES.includes(name as RoleName)
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
