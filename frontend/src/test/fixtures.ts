import type { Career, SubjectCareer } from '@/features/subjects'
import type { Group, Period } from '@/features/groups'

/**
 * Datos de prueba del catálogo académico.
 *
 * Reproduce el escenario del backend: una misma materia en dos carreras, grupos
 * propios y ajenos, y un grupo sin inscritos.
 */

export const sistemas: Career = {
  id_carrera: 1,
  nombre: 'Ingenieria de Sistemas',
  codigo: 'SIS',
  id_facultad: 1,
}

export const informatica: Career = {
  id_carrera: 2,
  nombre: 'Ingenieria Informatica',
  codigo: 'INF',
  id_facultad: 1,
}

export const periodoActivo: Period = {
  id_periodo: 3,
  nombre_periodo: '2-2026',
  gestion: 2026,
}

export function makeSubject(overrides: Partial<SubjectCareer> = {}): SubjectCareer {
  return {
    id_materia: 10,
    id_carrera: sistemas.id_carrera,
    nombre: 'Bases de Datos I',
    codigo: '2008034',
    descripcion: null,
    nivel_semestre: '5',
    obligatoria: true,
    activa: true,
    es_mia: true,
    cantidad_grupos: 2,
    carrera: sistemas,
    ...overrides,
  }
}

export function makeGroup(overrides: Partial<Group> = {}): Group {
  return {
    id_grupo: 100,
    num_grupo: '1',
    gestion: '2026',
    activo: true,
    es_mio: true,
    cantidad_estudiantes: 118,
    docente: { nombre_completo: 'Paola Careaga' },
    periodo: periodoActivo,
    ...overrides,
  }
}

/** Grupo de otro docente: se lista, pero no se abre. */
export function makeForeignGroup(overrides: Partial<Group> = {}): Group {
  return makeGroup({
    id_grupo: 200,
    num_grupo: '2',
    es_mio: false,
    cantidad_estudiantes: 64,
    docente: { nombre_completo: 'Victor Perez' },
    ...overrides,
  })
}

/** Grupo sin nómina, que es exactamente un grupo con cero inscritos. */
export function makeGroupWithoutRoster(overrides: Partial<Group> = {}): Group {
  return makeGroup({
    id_grupo: 300,
    num_grupo: '3',
    cantidad_estudiantes: 0,
    ...overrides,
  })
}

/** Respuesta cruda de `GET /materias`. */
export function subjectCatalogResponse(subjects: SubjectCareer[], mensaje: string | null = null) {
  return {
    data: subjects,
    meta: {
      total: subjects.length,
      total_mias: subjects.filter((subject) => subject.es_mia).length,
      id_periodo_activo: periodoActivo.id_periodo,
    },
    mensaje,
  }
}

/** Respuesta cruda de `GET /carreras/{id}/materias/{id}/grupos`. */
export function subjectGroupsResponse(subject: SubjectCareer, groups: Group[]) {
  return {
    data: { materia: subject, grupos: groups },
    meta: {
      total: groups.length,
      total_mios: groups.filter((group) => group.es_mio).length,
      id_periodo_activo: periodoActivo.id_periodo,
    },
  }
}

/** Respuesta cruda de `GET /grupos/{id}`. */
export function groupDetailResponse(
  group: Group,
  subject: SubjectCareer,
  esPeriodoActivo = true
) {
  return {
    data: { grupo: group, materia: subject },
    meta: {
      id_periodo_activo: periodoActivo.id_periodo,
      es_periodo_activo: esPeriodoActivo,
    },
  }
}
