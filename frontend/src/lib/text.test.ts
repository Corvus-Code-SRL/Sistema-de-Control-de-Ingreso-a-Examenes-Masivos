import { describe, expect, it } from 'vitest'
import { normalizeSearch } from './text'

describe('normalizeSearch', () => {
  it('ignora tildes, diéresis y mayúsculas', () => {
    expect(normalizeSearch('Cálculo I')).toBe('calculo i')
    expect(normalizeSearch('  PINGÜINO  ')).toBe('pinguino')
  })

  it('conserva la ñ como letra base n', () => {
    expect(normalizeSearch('Diseño')).toBe('diseno')
  })
})
