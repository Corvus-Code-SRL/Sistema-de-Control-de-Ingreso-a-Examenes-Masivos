import { mockApiWith, type RecordedRequest } from '@/test/http'
import type {
  RecordVersion,
  RectifiableRecord,
  RectifiableRecordDetail,
  RectifiableStudent,
  RectificationFaultType,
} from '@/features/risk-center/academic/types/riskRectification.types'

/**
 * Datos y servidor de mentira de la rectificación de la Central de Riesgo (HU-062).
 *
 * El servidor imita al real: busca por código SIS, carnet o nombre sin tildes, lista los registros del
 * estudiante, y al rectificar crea una versión nueva (la anterior pasa a «Anterior») o responde 409 si la
 * versión base ya no es la vigente.
 */

export const rectificationTypes: RectificationFaultType[] = [
  { id_falta: 1, nombre: 'Copia en examen', descripcion: null },
  { id_falta: 2, nombre: 'Suplantación de identidad', descripcion: null },
  { id_falta: 3, nombre: 'Material no permitido', descripcion: null },
  { id_falta: 4, nombre: 'Conducta indebida', descripcion: null },
]

export const mariaPerez: RectifiableStudent = {
  id_estudiante: 9700,
  nombre_completo: 'María Pérez Gómez',
  cod_sis: '202170101',
  ci: '7400101',
}

export const luisPerez: RectifiableStudent = {
  id_estudiante: 9701,
  nombre_completo: 'Luis Perez Soliz',
  cod_sis: '202170102',
  ci: '7400102',
}

export const carlaCondori: RectifiableStudent = {
  id_estudiante: 9702,
  nombre_completo: 'Carla Condori Vega',
  cod_sis: '202170103',
  ci: '7400103',
}

export const danielRojas: RectifiableStudent = {
  id_estudiante: 9703,
  nombre_completo: 'Daniel Rojas Mendoza',
  cod_sis: '202170104',
  ci: '7400104',
}

export const allStudents = [mariaPerez, luisPerez, carlaCondori, danielRojas]

export const ADMIN_NAME = 'Administrador del Sistema'
export const RECTIFIED_AT = '2026-11-14T15:30:00+00:00'

const type = (id: number) => rectificationTypes.find((candidate) => candidate.id_falta === id)!

function baseDetail(
  student: RectifiableStudent,
  id: number,
  registeredAt: string,
  exam: string
): Omit<RectifiableRecordDetail, 'historial' | 'tipo_falta' | 'descripcion' | 'estado' | 'numero_rectificaciones' | 'ultima_rectificacion' | 'version_vigente'> {
  return {
    id_cr_estudiante: id,
    fecha_registro: registeredAt,
    examen: { id_examen: id + 200, nombre: exam, materia: 'Cálculo I', fecha: '2026-09-10', carrera: 'Ing. de Sistemas' },
    aprobador: { nombre_completo: 'Marcelo Quiroga Andrade', fecha: registeredAt },
    estudiante: { id_estudiante: student.id_estudiante, nombre_completo: student.nombre_completo, cod_sis: student.cod_sis },
    ambiente: null,
    reportante: { nombre_completo: 'Daniela Ferrufino Soliz', fecha: registeredAt },
    evidencia: null,
  }
}

/** El registro 9961 de Carla ya fue rectificado una vez; el 9962 es original; María tiene el 9963. */
export function initialDetails(): RectifiableRecordDetail[] {
  const rectified: RectifiableRecordDetail = {
    ...baseDetail(carlaCondori, 9961, '2026-09-11T19:00:00+00:00', '1er Parcial'),
    tipo_falta: type(1),
    descripcion: 'El material contenía las respuestas.',
    estado: 'Rectificado',
    numero_rectificaciones: 1,
    ultima_rectificacion: '2026-10-05T14:00:00+00:00',
    version_vigente: 2,
    historial: [
      {
        numero_version: 2,
        estado: 'Vigente',
        tipo_falta: type(1),
        descripcion: 'El material contenía las respuestas.',
        motivo: 'La evidencia confirma que se trataba de copia.',
        autor: { nombre_completo: ADMIN_NAME },
        fecha: '2026-10-05T14:00:00+00:00',
      },
      {
        numero_version: 1,
        estado: 'Anterior',
        tipo_falta: type(3),
        descripcion: 'Tenía material impreso.',
        motivo: null,
        autor: { nombre_completo: 'Marcelo Quiroga Andrade' },
        fecha: '2026-09-11T19:00:00+00:00',
      },
    ],
  }

  return [rectified, original(carlaCondori, 9962, '2026-09-22T14:00:00+00:00', '2do Parcial', 4), original(mariaPerez, 9963, '2026-09-12T13:00:00+00:00', '1er Parcial', 1)]
}

function original(
  student: RectifiableStudent,
  id: number,
  registeredAt: string,
  exam: string,
  typeId: number
): RectifiableRecordDetail {
  const description = `Descripción original del registro ${id}.`

  return {
    ...baseDetail(student, id, registeredAt, exam),
    tipo_falta: type(typeId),
    descripcion: description,
    estado: 'Original',
    numero_rectificaciones: 0,
    ultima_rectificacion: null,
    version_vigente: 1,
    historial: [
      {
        numero_version: 1,
        estado: 'Vigente',
        tipo_falta: type(typeId),
        descripcion: description,
        motivo: null,
        autor: { nombre_completo: 'Marcelo Quiroga Andrade' },
        fecha: registeredAt,
      },
    ],
  }
}

function toSummary(detail: RectifiableRecordDetail): RectifiableRecord {
  return {
    id_cr_estudiante: detail.id_cr_estudiante,
    fecha_registro: detail.fecha_registro,
    examen: {
      id_examen: detail.examen.id_examen,
      nombre: detail.examen.nombre,
      materia: detail.examen.materia,
      fecha: detail.examen.fecha,
    },
    tipo_falta: detail.tipo_falta,
    aprobador: detail.aprobador,
    estado: detail.estado,
    numero_rectificaciones: detail.numero_rectificaciones,
    ultima_rectificacion: detail.ultima_rectificacion,
    version_vigente: detail.version_vigente,
  }
}

const plain = (text: string) =>
  text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()

interface StubOptions {
  students?: RectifiableStudent[]
  /** Fuerza la marca «hay más coincidencias». */
  hasMore?: boolean
  searchFail?: boolean
  typesFail?: boolean
  /** Otra persona rectificó antes: toda rectificación responde 409. */
  conflict?: boolean
}

export interface RectificationApi {
  calls: RecordedRequest[]
  details: Map<number, RectifiableRecordDetail>
  /** Cuerpos de los POST de rectificación recibidos. */
  posts: () => RecordedRequest[]
}

export function stubRectificationApi(options: StubOptions = {}): RectificationApi {
  const students = options.students ?? allStudents
  const details = new Map(initialDetails().map((detail) => [detail.id_cr_estudiante, detail]))

  const api = mockApiWith((request) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/rectificacion-riesgo/tipos-falta') {
      return options.typesFail
        ? { status: 500, body: { message: 'No se pudo cargar el catálogo.' } }
        : { body: { data: rectificationTypes } }
    }

    if (path === '/rectificacion-riesgo/estudiantes') {
      if (options.searchFail) return { status: 500, body: { message: 'No se pudo buscar estudiantes.' } }

      const term = (url.searchParams.get('q') ?? '').trim()
      const exact = students.filter((student) => student.cod_sis === term || student.ci === term)
      const words = plain(term).split(/\s+/).filter(Boolean)
      const found =
        exact.length > 0
          ? exact
          : /[a-z]/i.test(term)
            ? students.filter((student) => words.every((word) => plain(student.nombre_completo).includes(word)))
            : []

      return { body: { data: found, meta: { limite: 10, hay_mas: options.hasMore ?? false } } }
    }

    const records = path.match(/^\/rectificacion-riesgo\/estudiantes\/(\d+)\/registros$/)

    if (records) {
      const student = students.find((candidate) => candidate.id_estudiante === Number(records[1]))

      if (!student) return { status: 404, body: { message: 'No existe el estudiante indicado.' } }

      const rows = [...details.values()]
        .filter((detail) => detail.estudiante.id_estudiante === student.id_estudiante)
        .sort((left, right) => right.fecha_registro.localeCompare(left.fecha_registro))
        .map(toSummary)

      return { body: { data: { estudiante: student, registros: rows } } }
    }

    const rectify = path.match(/^\/rectificacion-riesgo\/registros\/(\d+)\/rectificar$/)

    if (rectify && request.method === 'POST') {
      const detail = details.get(Number(rectify[1]))
      const body = request.body as { id_tipo_falta: number; descripcion: string | null; motivo: string; version_base: number }

      if (!detail) return { status: 404, body: { message: 'No existe el registro de la Central de Riesgo indicado.' } }

      if (options.conflict || body.version_base !== detail.version_vigente) {
        return {
          status: 409,
          body: { message: 'El registro fue rectificado mientras usted lo editaba. Recargue para ver la versión vigente.' },
        }
      }

      const next: RecordVersion = {
        numero_version: detail.version_vigente + 1,
        estado: 'Vigente',
        tipo_falta: type(body.id_tipo_falta),
        descripcion: body.descripcion,
        motivo: body.motivo,
        autor: { nombre_completo: ADMIN_NAME },
        fecha: RECTIFIED_AT,
      }
      const updated: RectifiableRecordDetail = {
        ...detail,
        tipo_falta: next.tipo_falta,
        descripcion: next.descripcion,
        estado: 'Rectificado',
        numero_rectificaciones: detail.numero_rectificaciones + 1,
        ultima_rectificacion: RECTIFIED_AT,
        version_vigente: next.numero_version,
        historial: [next, ...detail.historial.map((version): RecordVersion => ({ ...version, estado: 'Anterior' }))],
      }
      details.set(updated.id_cr_estudiante, updated)

      return { status: 201, body: { data: updated } }
    }

    const show = path.match(/^\/rectificacion-riesgo\/registros\/(\d+)$/)

    if (show) {
      const detail = details.get(Number(show[1]))

      return detail
        ? { body: { data: detail } }
        : { status: 404, body: { message: 'No existe el registro de la Central de Riesgo indicado.' } }
    }

    return undefined
  })

  return {
    calls: api.calls,
    details,
    posts: () => api.calls.filter((call) => call.method === 'POST'),
  }
}
