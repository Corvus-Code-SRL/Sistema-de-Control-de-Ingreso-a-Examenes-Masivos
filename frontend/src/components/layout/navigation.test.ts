import { describe, expect, it } from 'vitest'
import { navigationByArea } from './navigation'

function labelsOf(area: keyof typeof navigationByArea): string[] {
  return navigationByArea[area].flatMap((group) => group.items.map((item) => item.label))
}

describe('navigationByArea', () => {
  it('incluye Ambientes solo en la navegación del administrador', () => {
    expect(labelsOf('administrador')).toContain('Ambientes')
    expect(labelsOf('docente')).not.toContain('Ambientes')
  })

  it('el auxiliar ve Mis exámenes y el control de ingreso', () => {
    expect(labelsOf('auxiliar')).toEqual(['Mis exámenes', 'Control de ingreso'])
  })
})
