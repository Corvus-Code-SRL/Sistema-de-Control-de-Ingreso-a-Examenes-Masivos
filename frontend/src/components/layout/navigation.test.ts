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

  it('ningún ítem lleva una insignia numérica inventada', () => {
    const items = Object.values(navigationByArea).flatMap((groups) => groups.flatMap((group) => group.items))

    expect(items.some((item) => 'badge' in item)).toBe(false)
  })

  it('Incidencias sigue listada y deshabilitada, sin destino', () => {
    const incidencias = navigationByArea.docente
      .flatMap((group) => group.items)
      .find((item) => item.label === 'Incidencias')

    expect(incidencias).toBeDefined()
    expect(incidencias?.to).toBeUndefined()
  })

  it('Materias del administrador solo se activa con la ruta exacta', () => {
    const items = navigationByArea.administrador.flatMap((group) => group.items)

    expect(items.find((item) => item.label === 'Materias')).toMatchObject({ to: '/materias', end: true })
    expect(items.find((item) => item.label === 'Asignar materia')).toMatchObject({ to: '/materias/asignar' })
  })
})
