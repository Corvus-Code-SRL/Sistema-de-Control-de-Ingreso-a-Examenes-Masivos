import { describe, expect, it } from 'vitest'
import { capabilitiesOf } from '@/features/auth'
import { NAVIGATION } from './navigation'
import { visibleSections } from './navigationSelectors'

type RoleName = 'Administrador' | 'Docente' | 'Auxiliar' | 'Invitado'

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

  it('las insignias son los contadores de invitaciones e incidencias pendientes', () => {
    const withBadge = NAVIGATION.flatMap((section) => section.items).filter((item) => item.badge)

    expect(withBadge.map((item) => [item.label, item.badge])).toEqual([
      ['Invitaciones recibidas', 'pendingInvitations'],
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
          ['Registrar cuenta nueva', '/cuentas/nueva'],
          ['Modificar rol', '/cuentas/roles']
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
          ['Bitácora', '/bitacora'],
          ['Generar reporte de bitácora', undefined],
          ['Reporte intentos a exámenes ajenos', '/reportes/intentos']
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
          ['Cargar nómina de estudiantes', '/cargar-nomina'],
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
          ['Invitaciones recibidas', '/invitaciones']
        ]
      },
      {
        label: 'Control de ingreso y examen en curso',
        items: [
          ['Control de ingreso', '/control-ingreso'],
          ['En curso', '/examenes/en-curso'],
          ['Registrar estudiante con retraso', '/retrasos'],
          ['Registrar situación excepcional', '/situacion-excepcional'],
          ['Reportar incidencia', '/reportar-incidencia']
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
          ['Historial de mis exámenes', '/historial'],
          ['Generar reporte de examen', '/examenes/reportes']
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
          ['Registrar situación excepcional', '/situacion-excepcional'],
          ['Reportar incidencia', '/reportar-incidencia'],
          ['En curso', '/examenes/en-curso'],
          ['Registrar estudiante con retraso', '/retrasos']
        ]
      }
    ])
  })

  it('un rol sin capacidades, o desconocido, no ve ningún menú', () => {
    expect(visibleSections([])).toEqual([])
    expect(visibleSections(capabilitiesOf('Rol inexistente'))).toEqual([])
    expect(visibleSections(capabilitiesOf(null))).toEqual([])
  })

  it('invitado: docencia y revisión de incidencias, sin «Mis auxiliares» ni la Central de riesgo', () => {
    const menu = menuOf('Invitado')
    const entries = menu.flatMap((section) => section.items.map(([label]) => label))

    expect(entries).not.toContain('Mis auxiliares')
    expect(entries).not.toContain('Central de riesgo')
    expect(entries).not.toContain('Invitar docentes colaboradores')
    expect(entries).toEqual(
      expect.arrayContaining([
        'Materias',
        'Registrar examen',
        'Habilitar auxiliar para el examen',
        'Control de ingreso',
        'En curso',
        'Registrar situación excepcional',
        'Reportar incidencia',
        'Registrar estudiante con retraso',
        'Revisar reportes de incidencias'
      ])
    )
  })

  it('invitado: la única entrada de su sección de riesgo es la revisión de incidencias', () => {
    const risk = menuOf('Invitado').find((section) => section.label === 'Central de riesgo')

    expect(risk?.items).toEqual([['Revisar reportes de incidencias', '/incidencias']])
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

  it('«Registrar situación excepcional» es visible para Docente, Invitado y Auxiliar, no para el Administrador', () => {
    for (const role of ['Docente', 'Invitado', 'Auxiliar'] as const) {
      const items = menuOf(role).flatMap((section) => section.items)

      expect(items).toContainEqual(['Registrar situación excepcional', '/situacion-excepcional'])
    }

    const adminEntries = menuOf('Administrador').flatMap((section) => section.items.map(([label]) => label))

    expect(adminEntries).not.toContain('Registrar situación excepcional')
  })

  it('«Registrar estudiante excepcional» ya no existe en ningún menú', () => {
    const labels = NAVIGATION.flatMap((section) => section.items.map((item) => item.label))

    expect(labels).not.toContain('Registrar estudiante excepcional')
  })

  it('la entrada de situaciones no depende de un examen: solo de la capacidad', () => {
    const items = NAVIGATION.flatMap((section) => section.items).filter(
      (item) => item.to === '/situacion-excepcional'
    )

    expect(items.map((item) => item.capability)).toEqual(['situaciones.registrar', 'situaciones.registrar'])
  })

  it('«Reportar incidencia» es visible para Docente, Invitado y Auxiliar, no para el Administrador', () => {
    for (const role of ['Docente', 'Invitado', 'Auxiliar'] as const) {
      const items = menuOf(role).flatMap((section) => section.items)

      expect(items).toContainEqual(['Reportar incidencia', '/reportar-incidencia'])
    }

    const adminEntries = menuOf('Administrador').flatMap((section) => section.items.map(([label]) => label))

    expect(adminEntries).not.toContain('Reportar incidencia')
  })

  it('«Reportar incidencia» no depende de un examen: solo de incidencias.registrar', () => {
    const items = NAVIGATION.flatMap((section) => section.items).filter((item) => item.to === '/reportar-incidencia')

    expect(items.map((item) => item.capability)).toEqual(['incidencias.registrar', 'incidencias.registrar'])
  })

  it.each(['Docente', 'Invitado', 'Auxiliar'] as const)(
    '%s con incidencias.registrar pero sin situaciones.registrar sigue viendo «Reportar incidencia»',
    (role) => {
      const capabilities = capabilitiesOf(role).filter((capability) => capability !== 'situaciones.registrar')
      const items = visibleSections(capabilities).flatMap((section) => section.items.map((item) => item.label))

      expect(capabilities).toContain('incidencias.registrar')
      expect(items).toContain('Reportar incidencia')
      expect(items).not.toContain('Registrar situación excepcional')
    }
  )

  it('«Bitácora» es visible solo para el Administrador (CA-11)', () => {
    expect(menuOf('Administrador').flatMap((section) => section.items)).toContainEqual(['Bitácora', '/bitacora'])

    for (const role of ['Docente', 'Invitado', 'Auxiliar'] as const) {
      const labels = menuOf(role).flatMap((section) => section.items.map(([label]) => label))

      expect(labels).not.toContain('Bitácora')
    }
  })

  it('«Bitácora» exige bitacora.consultar y no la capacidad genérica de administración', () => {
    const items = NAVIGATION.flatMap((section) => section.items).filter((item) => item.to === '/bitacora')

    expect(items.map((item) => item.capability)).toEqual(['bitacora.consultar'])
  })

  it('docente y auxiliar pueden abrir En curso', () => {
    for (const role of ['Docente', 'Auxiliar'] as const) {
      const items = menuOf(role).flatMap(
        (section) => section.items
      );

      expect(items).toContainEqual([
        'En curso',
        '/examenes/en-curso',
      ]);
    }
  });
})
