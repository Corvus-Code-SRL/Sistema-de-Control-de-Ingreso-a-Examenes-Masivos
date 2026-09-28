import type { Career, SubjectCareer } from '@/features/subjects'
import type { Group, Period } from '@/features/groups'
import type {
  RosterConfirmationData,
  RosterPreviewData,
  RosterPreviewRow,
} from '@/features/students'

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

/**
 * Datos de prueba de la carga de nómina.
 *
 * Reproduce lo que devuelven `/nomina/preview` y `/nomina/confirm`, incluida la
 * particularidad de `filas_validas`, que también cuenta a quienes ya están
 * inscritos en el grupo.
 */

export function makeRosterRow(overrides: Partial<RosterPreviewRow> = {}): RosterPreviewRow {
  return {
    numero_fila: 2,
    codigo_sis: '20260001',
    apellidos: 'PEREZ ROJAS',
    nombres: 'ANA MARIA',
    estado: 'new_student',
    errores: [],
    ...overrides,
  }
}

/**
 * Fila con un estado o un código que el frontend todavía no conoce.
 *
 * Los tipos no admiten esos valores, de ahí la conversión, pero la API sí podría
 * enviarlos: la interfaz tiene que seguir dibujando la fila.
 */
export function makeUnsupportedRosterRow(
  overrides: { estado?: string; errores?: string[] } = {}
): RosterPreviewRow {
  return { ...makeRosterRow(), ...overrides } as RosterPreviewRow
}

/** Respuesta cruda de `POST /grupos/{id}/nomina/preview`. */
export function rosterPreviewResponse(
  filas: RosterPreviewRow[],
  token = 'a'.repeat(64)
): { data: RosterPreviewData } {
  const inconsistentes = filas.filter((fila) => fila.estado === 'inconsistent').length

  return {
    data: {
      token,
      total_filas: filas.length,
      filas_validas: filas.length - inconsistentes,
      filas_inconsistentes: inconsistentes,
      filas,
    },
  }
}

/** Respuesta cruda de `POST /grupos/{id}/nomina/confirm`. */
export function rosterConfirmationResponse(
  overrides: Partial<RosterConfirmationData> = {}
): { data: RosterConfirmationData } {
  return {
    data: {
      total_filas: 3,
      filas_inconsistentes: 1,
      estudiantes_creados: 1,
      estudiantes_inscritos: 2,
      ya_inscritos: 0,
      ...overrides,
    },
  }
}

/**
 * Archivo de nómina de prueba.
 *
 * El tamaño se declara en lugar de materializarse: una prueba del límite de
 * 10 MB no puede reservar esa memoria.
 */
export function makeRosterFile(name = 'nomina.csv', size = 1024): File {
  const file = new File(['Estudiante,Apellidos,Nombres'], name)

  Object.defineProperty(file, 'size', { value: size })

  return file
}
