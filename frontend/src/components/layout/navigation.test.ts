import { describe, expect, it } from 'vitest'
import { capabilitiesOf } from '@/features/auth'
import { NAVIGATION } from './navigation'
import { visibleSections } from './navigationSelectors'

type RoleName = 'Administrador' | 'Docente' | 'Auxiliar'

/** El menú de un rol como lo vería su usuario: secciones y, dentro, [etiqueta, destino]. */
function menuOf(role: RoleName) {
  return visibleSections(capabilitiesOf(role)).map((section) => ({
    label: section.label,
    items: section.items.map((item) => [item.label, item.to] as const)
  }))
}

describe('NAVIGATION', () => {
  it('los ids de secciones y entradas no se repiten', () => {
    const ids = NAVIGATION.flatMap((section) => [section.id, ...section.items.map((item) => item.id)])

    expect(new Set(ids).size).toBe(ids.length)
  })

  it('ninguna entrada muestra códigos de historia ni avisos de estado en su texto', () => {
    const labels = NAVIGATION.flatMap((section) => [
      section.label,
      ...section.items.map((item) => item.label)
    ])

    for (const label of labels) {
      expect(label).not.toMatch(/\bHU-?\d+/i)
      expect(label).not.toMatch(/actual|sin ruta/i)
    }
    expect(labels).not.toContain('Inicio')
  })

  it('la única insignia es el contador de incidencias pendientes', () => {
    const withBadge = NAVIGATION.flatMap((section) => section.items).filter((item) => item.badge)

    expect(withBadge.map((item) => [item.label, item.badge])).toEqual([
      ['Revisar reportes de incidencias', 'pendingIncidents']
    ])
  })
})

describe('menú por capacidades', () => {
  it('administrador: 4 secciones con las entradas y destinos acordados', () => {
    expect(menuOf('Administrador')).toEqual([
      {
        label: 'Cuentas y roles',
        items: [
          ['Registrar cuenta nueva', '/cuentas'],
          ['Modificar rol', undefined]
        ]
      },
      {
        label: 'Catálogo académico y ambientes',
        items: [
          ['Catálogo académico', '/materias/catalogo'],
          ['Registrar facultad', undefined],
          ['Registrar carrera', undefined],
          ['Registrar materia', undefined],
          ['Asignar materia a carrera', '/materias/asignaciones'],
          ['Deshabilitar materia de carrera', undefined],
          ['Registrar ambiente', '/ambientes']
        ]
      },
      {
        label: 'Central de riesgo',
        items: [
          ['Consultar central', '/central-riesgo'],
          ['Rectificar registro de la central', undefined]
        ]
      },
      {
        label: 'Historial, auditoría y reportes',
        items: [
          ['Bitácora de operaciones', undefined],
          ['Generar reporte de bitácora', undefined]
        ]
      }
    ])
  })

  it('docente: 5 secciones con las entradas y destinos acordados', () => {
    expect(menuOf('Docente')).toEqual([
      {
        label: 'Grupos académicos y nóminas',
        items: [
          ['Materias', '/materias'],
          ['Mis grupos', '/mis-cursos'],
          ['Cargar nómina de estudiantes', undefined],
          ['Mis auxiliares', '/mis-auxiliares']
        ]
      },
      {
        label: 'Preparación de exámenes',
        items: [
          ['Programados', '/examenes/programados'],
          ['Registrar examen', '/examenes/nuevo'],
          ['Editar examen', undefined],
          ['Habilitar auxiliar para el examen', undefined],
          ['Cargar nómina de habilitados', undefined],
          ['Invitar docentes colaboradores', undefined],
          ['Invitaciones a otros exámenes', undefined]
        ]
      },
      {
        label: 'Control de ingreso y examen en curso',
        items: [
          ['Control de ingreso', '/control-ingreso'],
          ['En curso', undefined],
          ['Registrar estudiante con retraso', undefined],
          ['Registrar estudiante excepcional', undefined]
        ]
      },
      {
        label: 'Central de riesgo',
        items: [
          ['Revisar reportes de incidencias', '/incidencias'],
          ['Central de riesgo', '/central-riesgo']
        ]
      },
      {
        label: 'Historial de exámenes y reportes',
        items: [
          ['Historial de mis exámenes', undefined],
          ['Generar reporte de examen', undefined]
        ]
      }
    ])
  })

  it('auxiliar: 2 secciones con las entradas y destinos acordados', () => {
    expect(menuOf('Auxiliar')).toEqual([
      {
        label: 'Exámenes y grupos',
        items: [
          ['Mis grupos', undefined],
          ['Mis exámenes', '/mis-examenes']
        ]
      },
      {
        label: 'Control de ingreso y reportes',
        items: [
          ['Control de ingreso', '/control-ingreso'],
          ['En curso', undefined],
          ['Registrar estudiante con retraso', undefined]
        ]
      }
    ])
  })

  it('un rol sin capacidades, o desconocido, no ve ningún menú', () => {
    expect(visibleSections([])).toEqual([])
    expect(visibleSections(capabilitiesOf('Invitado'))).toEqual([])
    expect(visibleSections(capabilitiesOf(null))).toEqual([])
  })

  it('una sección sin ninguna entrada permitida no se devuelve', () => {
    // Con la capacidad de entrada pero sin las de sus entradas, la sección queda vacía y se omite.
    const sections = visibleSections(['administracion.gestionar'])

    expect(sections.map((section) => section.label)).toEqual([
      'Cuentas y roles',
      'Catálogo académico y ambientes',
      'Central de riesgo',
      'Historial, auditoría y reportes'
    ])
    expect(sections[2].items.map((item) => item.label)).toEqual(['Rectificar registro de la central'])
  })

  it('el Docente no ve secciones del Auxiliar, ni el Auxiliar las del Docente', () => {
    const teacher = menuOf('Docente').map((section) => section.label)
    const assistant = menuOf('Auxiliar').map((section) => section.label)

    expect(teacher).not.toContain('Exámenes y grupos')
    expect(teacher).not.toContain('Control de ingreso y reportes')
    expect(assistant).not.toContain('Control de ingreso y examen en curso')
    expect(assistant).not.toContain('Central de riesgo')
  })

  it('el Administrador no ve secciones del Docente aunque comparta la Central de riesgo', () => {
    const admin = visibleSections(capabilitiesOf('Administrador'))
    const riskSections = admin.filter((section) => section.label === 'Central de riesgo')

    expect(riskSections).toHaveLength(1)
    expect(riskSections[0].items.map((item) => item.label)).toEqual([
      'Consultar central',
      'Rectificar registro de la central'
    ])
  })
})
