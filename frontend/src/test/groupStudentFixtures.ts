import type { Group, GroupStudent } from '@/features/groups'
import type { SubjectCareer } from '@/features/subjects'
import { groupDetailResponse, makeGroup, makeSubject } from './fixtures'
import { mockApiWith } from './http'

/**
 * Datos de prueba de la lista de estudiantes del grupo (HU-020).
 *
 * El servidor simulado se comporta como el real: busca sin tildes ni mayúsculas por nombre o código
 * SIS (cada palabra debe coincidir), pagina de 50 en 50 y devuelve `total_inscritos` sin la búsqueda.
 */

export const STUDENTS_PER_PAGE = 50

export function makeGroupStudent(overrides: Partial<GroupStudent> = {}): GroupStudent {
  return {
    id_estudiante: 1,
    cod_sis: '202150001',
    apellido_paterno: 'Arnez',
    apellido_materno: 'Céspedes',
    nombre: 'Adriana',
    ...overrides,
  }
}

/** `count` estudiantes ya ordenados por apellido, como los devuelve el servidor. */
export function makeGroupStudents(count: number): GroupStudent[] {
  return Array.from({ length: count }, (_, index) =>
    makeGroupStudent({
      id_estudiante: 1000 + index,
      cod_sis: String(202160000 + index),
      apellido_paterno: `Apellido${String(index + 1).padStart(3, '0')}`,
      apellido_materno: null,
      nombre: `Estudiante ${index + 1}`,
    })
  )
}

const plain = (text: string) =>
  text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()

function matches(student: GroupStudent, q: string): boolean {
  const name = plain(
    [student.nombre, student.apellido_paterno, student.apellido_materno].filter(Boolean).join(' ')
  )

  return plain(q)
    .split(/\s+/)
    .filter(Boolean)
    .every((word) => name.includes(word) || student.cod_sis.includes(word))
}

/** Respuesta cruda de `GET /grupos/{id}/estudiantes`. */
export function groupStudentsResponse(students: GroupStudent[], q = '', page = 1) {
  const filtered = q === '' ? students : students.filter((student) => matches(student, q))
  const lastPage = Math.max(1, Math.ceil(filtered.length / STUDENTS_PER_PAGE))

  return {
    data: filtered.slice((page - 1) * STUDENTS_PER_PAGE, page * STUDENTS_PER_PAGE),
    meta: {
      current_page: page,
      last_page: lastPage,
      per_page: STUDENTS_PER_PAGE,
      total: filtered.length,
      total_inscritos: students.length,
    },
  }
}

interface GroupApiOptions {
  group?: Group
  subject?: SubjectCareer
  esPeriodoActivo?: boolean
  students?: GroupStudent[]
  /** Fuerza una respuesta de error en la lista de estudiantes. */
  studentsStatus?: number
  /** Fuerza una respuesta de error en el detalle del grupo. */
  detailStatus?: number
}

/** Simula el detalle del grupo y su lista de estudiantes; devuelve las llamadas para inspeccionarlas. */
export function stubGroupApi({
  group = makeGroup(),
  subject = makeSubject(),
  esPeriodoActivo = true,
  students = [],
  studentsStatus,
  detailStatus,
}: GroupApiOptions = {}) {
  return mockApiWith(({ url }) => {
    const { pathname, searchParams } = new URL(url, 'http://localhost')

    if (/\/grupos\/\d+\/estudiantes$/.test(pathname)) {
      if (studentsStatus) {
        return { status: studentsStatus, body: { message: 'No se pudo leer la lista de estudiantes.' } }
      }

      return {
        body: groupStudentsResponse(
          students,
          searchParams.get('q') ?? '',
          Number(searchParams.get('page') ?? '1')
        ),
      }
    }

    if (/\/grupos\/\d+$/.test(pathname)) {
      if (detailStatus) {
        return { status: detailStatus, body: { message: 'Error interno del servidor.' } }
      }

      return { body: groupDetailResponse(group, subject, esPeriodoActivo) }
    }

    if (pathname.endsWith('/periodos')) {
      return {
        body: {
          data: [{ ...group.periodo, vigente: true }],
          meta: { id_periodo_activo: group.periodo.id_periodo },
        },
      }
    }

    return undefined
  })
}
