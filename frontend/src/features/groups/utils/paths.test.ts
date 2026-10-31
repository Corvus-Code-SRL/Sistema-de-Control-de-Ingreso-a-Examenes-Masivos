import { describe, expect, it } from 'vitest'
import { groupIdFrom, groupDetailPath, groupExamsPath, groupAssistantsPath, groupCreateExamPath } from './paths'

describe('groupIdFrom', () => {
  it('acepta un entero positivo de hasta 9 dígitos', () => {
    expect(groupIdFrom('1')).toBe(1)
    expect(groupIdFrom('999999999')).toBe(999999999)
  })

  it.each([[undefined], [''], ['0'], ['007'], ['-1'], ['1.5'], ['abc'], ['1234567890'], ['2147483648']])(
    'rechaza %s',
    (value) => {
      expect(groupIdFrom(value)).toBeNull()
    }
  )
})

describe('rutas contextuales', () => {
  it('conserva el identificador del grupo en cada destino', () => {
    expect(groupDetailPath(100)).toBe('/grupos/100')
    expect(groupExamsPath(100)).toBe('/grupos/100/examenes')
    expect(groupAssistantsPath(100)).toBe('/grupos/100/auxiliares')
    expect(groupCreateExamPath(100)).toBe('/examenes/nuevo?grupo=100')
  })
})
