import { describe, expect, it } from 'vitest'
import { areaForRole, capabilitiesOf, hasCapability } from './capabilities'

describe('capacidades por rol', () => {
  it('situaciones excepcionales permite docente y auxiliar, pero no administrador', () => {
    expect(hasCapability('Docente', 'situaciones.registrar')).toBe(true)
    expect(hasCapability('Auxiliar', 'situaciones.registrar')).toBe(true)
    expect(hasCapability('Administrador', 'situaciones.registrar')).toBe(false)
    expect(hasCapability(null, 'situaciones.registrar')).toBe(false)
  })

  it('el Docente incluye lo operativo del Auxiliar, salvo lo propio de la auxiliatura', () => {
    const own: string[] = ['asignaciones.consultar']

    for (const capability of capabilitiesOf('Auxiliar')) {
      expect(hasCapability('Docente', capability)).toBe(!own.includes(capability))
    }
  })

  it('consultar las asignaciones como auxiliar es solo del Auxiliar', () => {
    expect(hasCapability('Auxiliar', 'asignaciones.consultar')).toBe(true)
    expect(hasCapability('Docente', 'asignaciones.consultar')).toBe(false)
    expect(hasCapability('Administrador', 'asignaciones.consultar')).toBe(false)
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

  it('la Central de Riesgo la consultan el Docente y el Administrador, no el Auxiliar', () => {
    expect(hasCapability('Docente', 'riesgo.consultar')).toBe(true)
    expect(hasCapability('Administrador', 'riesgo.consultar')).toBe(true)
    expect(hasCapability('Auxiliar', 'riesgo.consultar')).toBe(false)
    expect(hasCapability(null, 'riesgo.consultar')).toBe(false)
  })

  it('un rol desconocido o ausente no concede nada', () => {
    expect(capabilitiesOf('Rol inexistente')).toEqual([])
    expect(capabilitiesOf(null)).toEqual([])
    expect(hasCapability(undefined, 'ingreso.operar')).toBe(false)
  })

  it('el área sale del rol', () => {
    expect(areaForRole('Administrador')).toBe('administrador')
    expect(areaForRole('Docente')).toBe('docente')
    expect(areaForRole('Auxiliar')).toBe('auxiliar')
    expect(areaForRole('Invitado')).toBe('docente')
    expect(areaForRole(null)).toBe('docente')
  })
})