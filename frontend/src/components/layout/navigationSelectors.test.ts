import { Clock, DoorOpen } from 'lucide-react'
import { describe, expect, it } from 'vitest'
import { capabilitiesOf } from '@/features/auth'
import type { NavSection } from './navigation'
import { findActiveItem, visibleSections } from './navigationSelectors'

type RoleName = 'Administrador' | 'Docente' | 'Auxiliar'

function activeItemId(role: RoleName, pathname: string): string | null {
  return findActiveItem(visibleSections(capabilitiesOf(role)), pathname)?.itemId ?? null
}

describe('findActiveItem', () => {
  it.each([
    ['/materias', 'teacher-subjects'],
    ['/carreras/3/materias/7/grupos', 'teacher-subjects'],
    ['/mis-cursos', 'teacher-my-groups'],
    ['/cursos/5', 'teacher-my-groups'],
    ['/grupos/5', 'teacher-my-groups'],
    ['/grupos/5/editar', 'teacher-my-groups'],
    ['/grupos/5/nomina', 'teacher-my-groups'],
    ['/grupos/5/examenes', 'teacher-my-groups'],
    ['/grupos/5/auxiliares', 'teacher-my-groups'],
    ['/mis-auxiliares', 'teacher-my-assistants'],
    ['/examenes/programados', 'teacher-scheduled-exams'],
    ['/examenes/12', 'teacher-scheduled-exams'],
    ['/examenes/12/editar', 'teacher-scheduled-exams'],
    ['/examenes/12/auxiliares', 'teacher-scheduled-exams'],
    ['/examenes/nuevo', 'teacher-create-exam'],
    ['/control-ingreso', 'teacher-entry-control'],
    ['/examenes/12/control-ingreso', 'teacher-entry-control'],
    ['/examenes/en-curso', 'teacher-exam-in-progress'],
    ['/examenes/12/en-curso', 'teacher-exam-in-progress'],
    ['/incidencias', 'teacher-incident-reports'],
    ['/central-riesgo', 'teacher-risk-query'],
    ['/central-riesgo/9941', 'teacher-risk-query']
  ])('docente: %s resalta %s', (pathname, expected) => {
    expect(activeItemId('Docente', pathname)).toBe(expected)
  })

  it.each([
    ['/cuentas', 'admin-register-account'],
    ['/cuentas/00000000-0000-4000-8000-000000000011', 'admin-register-account'],
    ['/materias/catalogo', 'admin-academic-catalog'],
    ['/materias/asignaciones', 'admin-assign-subject'],
    ['/ambientes', 'admin-register-classroom'],
    ['/central-riesgo', 'admin-risk-query'],
    ['/central-riesgo/9941', 'admin-risk-query']
  ])('administrador: %s resalta %s', (pathname, expected) => {
    expect(activeItemId('Administrador', pathname)).toBe(expected)
  })

  it.each([
    ['/mis-examenes', 'assistant-my-exams'],
    ['/control-ingreso', 'assistant-entry-control'],
    ['/examenes/3/control-ingreso', 'assistant-entry-control'],
    ['/examenes/en-curso', 'assistant-exam-in-progress'],
    ['/examenes/3/en-curso', 'assistant-exam-in-progress']
  ])('auxiliar: %s resalta %s', (pathname, expected) => {
    expect(activeItemId('Auxiliar', pathname)).toBe(expected)
  })

  it('devuelve también la sección que contiene la entrada activa', () => {
    const sections = visibleSections(capabilitiesOf('Docente'))

    expect(findActiveItem(sections, '/grupos/5/editar')).toEqual({
      sectionId: 'teacher-groups',
      itemId: 'teacher-my-groups'
    })
  })

  it('una ruta que ninguna entrada reclama no resalta nada', () => {
    expect(activeItemId('Docente', '/ruta-desconocida')).toBeNull()
    expect(activeItemId('Auxiliar', '/materias')).toBeNull()
  })

  it('no confunde rutas que solo comparten el comienzo del texto', () => {
    expect(activeItemId('Docente', '/materiasX')).toBeNull()
    expect(activeItemId('Docente', '/mis-cursos-viejos')).toBeNull()
  })

  it('la entrada más específica gana a la que cubre toda una raíz', () => {
    // /examenes/nuevo también cae bajo /examenes/:examId/*, pero tiene más segmentos fijos.
    expect(activeItemId('Docente', '/examenes/nuevo')).toBe('teacher-create-exam')
    expect(activeItemId('Docente', '/examenes/7/control-ingreso')).toBe('teacher-entry-control')
  })

  it('una entrada deshabilitada nunca es la activa, aunque declare patrones', () => {
    const navigation: NavSection[] = [
      {
        id: 'section',
        label: 'Sección',
        icon: DoorOpen,
        capability: 'examenes.gestionar',
        items: [
          {
            id: 'pending',
            label: 'Pendiente',
            icon: Clock,
            capability: 'examenes.gestionar',
            matches: ['/examenes/:examId/*']
          }
        ]
      }
    ]

    expect(findActiveItem(navigation, '/examenes/4/editar')).toBeNull()
  })

  it('a igual especificidad gana la entrada declarada primero', () => {
    const navigation: NavSection[] = [
      {
        id: 'section',
        label: 'Sección',
        icon: DoorOpen,
        capability: 'examenes.gestionar',
        items: ['first', 'second'].map((id) => ({
          id,
          label: id,
          icon: Clock,
          capability: 'examenes.gestionar' as const,
          to: '/misma'
        }))
      }
    ]

    expect(findActiveItem(navigation, '/misma')?.itemId).toBe('first')
  })
})
