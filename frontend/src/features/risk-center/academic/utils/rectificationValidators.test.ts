import { describe, expect, it } from 'vitest'
import { validateReason, validateSearchTerm } from './rectificationValidators'

describe('validateSearchTerm', () => {
  it.each(['', '   '])('rechaza un dato vacío (%j)', (term) => {
    expect(validateSearchTerm(term)).toBe(
      'Ingrese el código SIS, el carnet de identidad o el nombre del estudiante.'
    )
  })

  it('el nombre exige al menos tres caracteres', () => {
    expect(validateSearchTerm('pe')).toBe('Para buscar por nombre ingrese al menos 3 caracteres.')
    expect(validateSearchTerm('per')).toBeNull()
  })

  it('un código o carnet numérico se busca exacto sin mínimo de nombre', () => {
    expect(validateSearchTerm('12')).toBeNull()
    expect(validateSearchTerm('202312345')).toBeNull()
  })
})

describe('validateReason', () => {
  it('el motivo es obligatorio', () => {
    expect(validateReason('')).toBe('El motivo es obligatorio')
    expect(validateReason('     ')).toBe('El motivo es obligatorio')
  })

  it('el motivo tiene entre 10 y 500 caracteres', () => {
    expect(validateReason('corto')).toBe('El motivo debe tener al menos 10 caracteres.')
    expect(validateReason('a'.repeat(10))).toBeNull()
    expect(validateReason('a'.repeat(500))).toBeNull()
    expect(validateReason('a'.repeat(501))).toBe('El motivo no puede superar los 500 caracteres.')
  })

  it('cuenta los caracteres sin los espacios de los extremos', () => {
    expect(validateReason('   corto   ')).toBe('El motivo debe tener al menos 10 caracteres.')
    expect(validateReason(`  ${'a'.repeat(500)}  `)).toBeNull()
  })
})
