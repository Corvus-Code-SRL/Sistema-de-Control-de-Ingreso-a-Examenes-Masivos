import { describe, expect, it } from 'vitest'
import { isGuestCode, normalizeSisCode, roleCodeMismatch, sisCodeError } from './sisCode'

const YEAR = 2026

describe('normalizeSisCode', () => {
  it('pasa a mayúsculas y quita los espacios sobrantes', () => {
    expect(normalizeSisCode('  adm 0001 ')).toBe('ADM 0001')
    expect(normalizeSisCode('inv4821')).toBe('INV4821')
  })
})

describe('sisCodeError', () => {
  it.each([
    ['202312345', 'Auxiliar'],
    ['199000001', 'Auxiliar'],
    ['90123456', 'Auxiliar'],
    ['ADM0001', 'Administrador'],
    ['ADM13352440', 'Administrador'],
    ['10452', 'Docente'],
    ['abc12', 'Docente'],
    ['10452', null],
  ])('acepta %s para %s', (code, role) => {
    expect(sisCodeError(code, role, YEAR)).toBeNull()
  })

  it.each([
    ['202712345', 'Auxiliar'],
    ['198912345', 'Auxiliar'],
    ['80123456', 'Auxiliar'],
    ['12345', 'Auxiliar'],
    ['2023123', 'Auxiliar'],
    ['0001ADM', 'Administrador'],
    ['10452', 'Administrador'],
    ['AB', 'Docente'],
    ['AB-12', 'Docente'],
  ])('rechaza %s para %s', (code, role) => {
    expect(sisCodeError(code, role, YEAR)).not.toBeNull()
  })

  it('exige el código y limita su largo', () => {
    expect(sisCodeError('   ', null, YEAR)).toBe('Ingrese el código SIS.')
    expect(sisCodeError('A'.repeat(16), null, YEAR)).toMatch(/más de 15/)
  })

  it('reserva el prefijo INV para el sistema y el rol Invitado para los docentes sin código SIS', () => {
    expect(sisCodeError('INV4821', 'Docente', YEAR)).toMatch(/genera el sistema/)
    expect(sisCodeError('10452', 'Invitado', YEAR)).toMatch(/genera el sistema/)
  })
})

describe('roleCodeMismatch', () => {
  it('un código de estudiante de 9 dígitos no encaja con Administrador', () => {
    expect(roleCodeMismatch('202312345', 'Administrador', YEAR)).toBe(
      'Este código no corresponde a una cuenta de administrador.'
    )
  })

  it.each([
    ['202312345', 'Docente'],
    ['202312345', 'Auxiliar'],
    ['ADM0001', 'Administrador'],
    ['ADM0001', 'Docente'],
    ['10452', 'Docente'],
  ])('%s encaja con %s', (code, role) => {
    expect(roleCodeMismatch(code, role, YEAR)).toBeNull()
  })

  it.each([
    ['10452', 'Auxiliar', 'auxiliar'],
    ['ADM0001', 'Auxiliar', 'auxiliar'],
    ['AB', 'Docente', 'docente'],
    ['10452', 'Administrador', 'administrador'],
  ])('%s no encaja con %s', (code, role, noun) => {
    expect(roleCodeMismatch(code, role, YEAR)).toBe(`Este código no corresponde a una cuenta de ${noun}.`)
  })

  it('refleja el servidor: el año del auxiliar no puede ser futuro ni anterior a 1990', () => {
    expect(roleCodeMismatch('202712345', 'Auxiliar', YEAR)).not.toBeNull()
    expect(roleCodeMismatch('198912345', 'Auxiliar', YEAR)).not.toBeNull()
    expect(roleCodeMismatch('90123456', 'Auxiliar', YEAR)).toBeNull()
  })

  it('un rol sin formato propio no se evalúa', () => {
    expect(roleCodeMismatch('10452', 'Invitado', YEAR)).toBeNull()
  })
})

describe('isGuestCode', () => {
  it('reconoce el prefijo INV', () => {
    expect(isGuestCode('INV4821')).toBe(true)
    expect(isGuestCode('10452')).toBe(false)
  })
})
