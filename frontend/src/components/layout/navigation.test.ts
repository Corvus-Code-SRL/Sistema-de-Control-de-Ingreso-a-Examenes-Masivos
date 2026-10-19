import { describe, expect, it } from 'vitest'
import { navigationByArea } from './navigation'

function labelsOf(area: keyof typeof navigationByArea): string[] {
  return navigationByArea[area].flatMap((group) =>
    group.items.flatMap((item) => [item.label, ...(item.children ?? []).map((child) => child.label)])
  )
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

  it('Incidencias lleva a /incidencias', () => {
    const incidencias = navigationByArea.docente
      .flatMap((group) => group.items)
      .find((item) => item.label === 'Incidencias')

    expect(incidencias).toBeDefined()
    expect(incidencias?.to).toBe('/incidencias')
  })

  it('el administrador tiene un encabezado Materias sin enlace con Asignaciones y Catálogo debajo', () => {
    const items = navigationByArea.administrador.flatMap((group) => group.items)
    const header = items.find((item) => item.label === 'Materias')

    expect(items.filter((item) => item.label === 'Materias')).toHaveLength(1)
    expect(header?.to).toBeUndefined()
    expect(header?.children?.map((child) => [child.label, child.to])).toEqual([
      ['Asignaciones', '/materias/asignaciones'],
      ['Catálogo', '/materias/catalogo'],
    ])
    expect(items.find((item) => item.label === 'Asignar materia')).toBeUndefined()
    expect(items.some((item) => item.to === '/materias/asignar' || item.to === '/materias')).toBe(false)
  })

  it('la navegación del docente conserva Materias como enlace directo a /materias', () => {
    const items = navigationByArea.docente.flatMap((group) => group.items)

    expect(items.find((item) => item.label === 'Materias')).toMatchObject({ to: '/materias' })
    expect(items.some((item) => item.children !== undefined)).toBe(false)
  })

  it('el docente tiene Registros hacia /central-riesgo y Verificar antecedentes sin destino', () => {
    const items = navigationByArea.docente.flatMap((group) => group.items)

    expect(items.find((item) => item.label === 'Registros')).toMatchObject({ to: '/central-riesgo' })
    expect(items.find((item) => item.label === 'Verificar antecedentes')?.to).toBeUndefined()
  })

  it('el administrador tiene el grupo Supervisión con Central de riesgo y la Bitácora pendiente', () => {
    const group = navigationByArea.administrador.find((candidate) => candidate.label === 'Supervisión')
    const administration = navigationByArea.administrador.find((candidate) => candidate.label === 'Administración')

    expect(group?.items.map((item) => [item.label, item.to])).toEqual([
      ['Central de riesgo', '/central-riesgo'],
      ['Bitácora', undefined],
    ])
    expect(administration?.items.map((item) => item.label)).not.toContain('Bitácora')
    expect(administration?.items.map((item) => item.label)).toEqual([
      'Cuentas',
      'Materias',
      'Facultades',
      'Ambientes',
      'Carreras',
    ])
  })

  it('el auxiliar no tiene acceso a la Central de riesgo', () => {
    expect(labelsOf('auxiliar')).not.toContain('Registros')
    expect(labelsOf('auxiliar')).not.toContain('Central de riesgo')
  })
})
