import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { ACCESS_BY_FEATURE, hasCapability, type AccessFeature } from './capabilities'

/**
 * El mapa de acceso por función vive en dos lugares: `config('sciem.access')` del backend y
 * `ACCESS_BY_FEATURE` de la interfaz. Esta prueba lee el PHP y falla si difieren, para que cambiar el
 * acceso de un rol en un solo lado no deje al menú mostrando lo que el servidor rechaza (o al revés).
 */

const BACKEND = resolve(process.cwd(), '..', 'backend')

/** `Role::INVITADO` → 'Invitado', leído de las constantes del modelo. */
function backendRoleConstants(): Record<string, string> {
  const source = readFileSync(resolve(BACKEND, 'app/Models/Role.php'), 'utf8')
  const constants: Record<string, string> = {}

  for (const match of source.matchAll(/const\s+([A-Z_]+)\s*=\s*'([^']+)'/g)) {
    constants[match[1]] = match[2]
  }

  return constants
}

function backendAccess(): Record<string, string[]> {
  const source = readFileSync(resolve(BACKEND, 'config/sciem.php'), 'utf8')
  const block = /'access'\s*=>\s*\[([\s\S]*?)\n\s{4}\],/.exec(source)

  if (!block) throw new Error("No se encontró el bloque 'access' en backend/config/sciem.php")

  const constants = backendRoleConstants()
  const access: Record<string, string[]> = {}

  for (const entry of block[1].matchAll(/'([a-z_]+)'\s*=>\s*\[([^\]]*)\]/g)) {
    access[entry[1]] = [...entry[2].matchAll(/Role::([A-Z_]+)/g)].map((role) => {
      const name = constants[role[1]]

      if (!name) throw new Error(`Role::${role[1]} no existe en app/Models/Role.php`)

      return name
    })
  }

  return access
}

describe('mapa de acceso: backend e interfaz', () => {
  const backend = backendAccess()

  it('declaran las mismas funciones', () => {
    expect(Object.keys(ACCESS_BY_FEATURE).sort()).toEqual(Object.keys(backend).sort())
  })

  it.each(Object.keys(ACCESS_BY_FEATURE) as AccessFeature[])('%s: los mismos roles', (feature) => {
    expect([...ACCESS_BY_FEATURE[feature]].sort()).toEqual([...backend[feature]].sort())
  })
})

describe('capacidades del Invitado y del Docente', () => {
  it('el Invitado ejerce la docencia, habilita auxiliares por examen y revisa incidencias', () => {
    for (const capability of [
      'grupos.gestionar',
      'examenes.gestionar',
      'ingreso.operar',
      'incidencias.registrar',
      'auxiliares.examen',
      'incidencias.revisar',
    ] as const) {
      expect(hasCapability('Invitado', capability)).toBe(true)
    }
  })

  it('el Invitado no consulta la Central de Riesgo ni gestiona auxiliares de grupo', () => {
    for (const capability of [
      'riesgo.consultar',
      'auxiliares.gestionar',
      'auxiliares.reportar',
      'casos-especiales.gestionar',
      'docentes.invitar',
      'administracion.gestionar',
      'asignaciones.consultar',
    ] as const) {
      expect(hasCapability('Invitado', capability)).toBe(false)
    }
  })

  it('el Docente conserva todas las funciones configurables', () => {
    for (const capability of [
      'grupos.gestionar',
      'examenes.gestionar',
      'ingreso.operar',
      'incidencias.registrar',
      'incidencias.revisar',
      'auxiliares.examen',
      'auxiliares.gestionar',
      'auxiliares.reportar',
      'casos-especiales.gestionar',
      'docentes.invitar',
      'riesgo.consultar',
    ] as const) {
      expect(hasCapability('Docente', capability)).toBe(true)
    }
  })
})
