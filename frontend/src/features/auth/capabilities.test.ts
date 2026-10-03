import { describe, expect, it } from 'vitest'
import { areaForRole, capabilitiesOf, hasCapability } from './capabilities'

describe('capacidades por rol', () => {
  it('el Docente incluye todo lo que puede el Auxiliar', () => {
    for (const capability of capabilitiesOf('Auxiliar')) {
      expect(hasCapability('Docente', capability)).toBe(true)
    }
  })

  it('el Docente puede más que el Auxiliar', () => {
    expect(hasCapability('Docente', 'examenes.gestionar')).toBe(true)
    expect(hasCapability('Auxiliar', 'examenes.gestionar')).toBe(false)
    expect(hasCapability('Auxiliar', 'grupos.gestionar')).toBe(false)
  })

  it('el Auxiliar opera el ingreso', () => {
    expect(hasCapability('Auxiliar', 'ingreso.operar')).toBe(true)
  })

  it('la administración es solo del Administrador', () => {
    expect(hasCapability('Administrador', 'administracion.gestionar')).toBe(true)
    expect(hasCapability('Docente', 'administracion.gestionar')).toBe(false)
    expect(hasCapability('Auxiliar', 'administracion.gestionar')).toBe(false)
  })

  it('un rol desconocido o ausente no concede nada', () => {
    expect(capabilitiesOf('Invitado')).toEqual([])
    expect(capabilitiesOf(null)).toEqual([])
    expect(hasCapability(undefined, 'ingreso.operar')).toBe(false)
  })

  it('el área sale del rol', () => {
    expect(areaForRole('Administrador')).toBe('administrador')
    expect(areaForRole('Docente')).toBe('docente')
    expect(areaForRole('Auxiliar')).toBe('docente')
    expect(areaForRole(null)).toBe('docente')
  })
})
