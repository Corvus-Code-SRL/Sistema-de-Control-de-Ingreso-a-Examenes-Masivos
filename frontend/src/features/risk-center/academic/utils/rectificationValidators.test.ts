import { describe, expect, it } from 'vitest'
import {
  hasErrors,
  normalizeDescription,
  validateRectification,
  validateSearchTerm,
} from './rectificationValidators'

const current = { faultTypeId: 1, description: 'Descripción vigente.' }

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

describe('normalizeDescription', () => {
  it('recorta y convierte lo vacío en null', () => {
    expect(normalizeDescription('  texto  ')).toBe('texto')
    expect(normalizeDescription('   ')).toBeNull()
    expect(normalizeDescription(null)).toBeNull()
  })
})

describe('validateRectification', () => {
  const valid = { faultTypeId: 2, description: 'Descripción vigente.', reason: 'Se confirmó otra falta.' }

  it('acepta un cambio de tipo con motivo válido', () => {
    expect(hasErrors(validateRectification(valid, current))).toBe(false)
  })

  it('acepta un cambio solo de descripción', () => {
    const errors = validateRectification({ ...valid, faultTypeId: 1, description: 'Otra.' }, current)

    expect(hasErrors(errors)).toBe(false)
  })

  it('el motivo es obligatorio', () => {
    expect(validateRectification({ ...valid, reason: '' }, current).reason).toBe('El motivo es obligatorio')
    expect(validateRectification({ ...valid, reason: '     ' }, current).reason).toBe('El motivo es obligatorio')
  })

  it('el motivo tiene entre 10 y 500 caracteres', () => {
    expect(validateRectification({ ...valid, reason: 'corto' }, current).reason).toBe(
      'El motivo debe tener al menos 10 caracteres.'
    )
    expect(validateRectification({ ...valid, reason: 'a'.repeat(10) }, current).reason).toBeUndefined()
    expect(validateRectification({ ...valid, reason: 'a'.repeat(500) }, current).reason).toBeUndefined()
    expect(validateRectification({ ...valid, reason: 'a'.repeat(501) }, current).reason).toBe(
      'El motivo no puede superar los 500 caracteres.'
    )
  })

  it('la descripción no supera los 2000 caracteres', () => {
    expect(validateRectification({ ...valid, description: 'a'.repeat(2001) }, current).description).toBe(
      'La descripción no puede superar los 2000 caracteres.'
    )
  })

  it('rechaza una rectificación sin cambios, aunque difiera en espacios', () => {
    const same = { faultTypeId: 1, description: '  Descripción vigente.  ', reason: 'Motivo suficiente.' }

    expect(validateRectification(same, current).unchanged).toBe(
      'No hay cambios: modifique el tipo de falta o la descripción para rectificar.'
    )
  })

  it('vaciar una descripción que existía es un cambio', () => {
    const errors = validateRectification({ faultTypeId: 1, description: '', reason: 'Motivo suficiente.' }, current)

    expect(errors.unchanged).toBeUndefined()
  })

  it('sin descripción antes y ahora, y el mismo tipo, no hay cambios', () => {
    const errors = validateRectification(
      { faultTypeId: 1, description: '  ', reason: 'Motivo suficiente.' },
      { faultTypeId: 1, description: null }
    )

    expect(errors.unchanged).toBeDefined()
  })

  it('pide el tipo de falta si no hay ninguno elegido', () => {
    expect(validateRectification({ ...valid, faultTypeId: null }, current).faultType).toBe(
      'Seleccione el tipo de falta.'
    )
  })
})
