import { courseTitle } from '@/features/groups'
import type { Course } from '@/features/groups'

/**
 * Grupos donde se puede cargar la nómina: los activos del docente. El período vigente y que el
 * grupo sea suyo los vuelve a comprobar el detalle del grupo elegido.
 */
export function uploadableCourses(courses: readonly Course[]): Course[] {
  return courses.filter((course) => course.group.activo && course.group.es_mio)
}

/** «Bases de Datos I · Grupo 1 · Ingeniería de Sistemas»: la misma materia se dicta en varias carreras. */
export function courseOptionLabel(course: Course): string {
  return `${courseTitle(course.subject, course.group)} · ${course.subject.carrera.nombre}`
}
