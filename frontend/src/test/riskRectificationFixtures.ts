import { mockApiWith, type RecordedRequest } from '@/test/http'
import type {
  RectifiableRecord,
  RectifiableRecordDetail,
  RectifiableStudent,
  RectifiedRecord,
} from '@/features/risk-center/academic/types/riskRectification.types'

/**
 * Datos y servidor de mentira de la rectificación de la Central de Riesgo (HU-062).
 *
 * El servidor imita al real: busca por código SIS, carnet o nombre sin tildes, separa los registros activos de
 * los rectificados, responde 404 al abrir uno ya rectificado y, al rectificar, lo marca como retirado
 * (200) o responde 409 si ya lo estaba.
 */

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

/** El motivo del registro que ya viene rectificado en los datos iniciales. */
export const VOIDED_REASON = 'Se confundió a la estudiante: el reporte correspondía a otra persona.'

const APPROVER = { nombre_completo: 'Marcelo Quiroga Andrade' }

function detail(
  student: RectifiableStudent,
  id: number,
  registeredAt: string,
  exam: string,
  faultId: number,
  faultName: string,
  overrides: Partial<RectifiableRecordDetail> = {}
): RectifiableRecordDetail {
  return {
    id_cr_estudiante: id,
    estudiante: student,
    examen: { id_examen: id + 200, nombre: exam, materia: 'Cálculo I', fecha: '2026-09-10', carrera: 'Ing. de Sistemas' },
    hecho: { fecha_hora: '2026-09-10T12:12:00+00:00' },
    ambiente: { nro_aula: '691A' },
    tipo_falta: { id_falta: faultId, nombre: faultName },
    descripcion: `Descripción del registro ${id}.`,
    evidencia: null,
    reportante: { nombre_completo: 'Daniela Ferrufino Soliz', fecha: '2026-09-10T12:40:00+00:00' },
    aprobador: { ...APPROVER, fecha: registeredAt },
    fecha_registro: registeredAt,
    ...overrides,
  }
}

export interface StubbedRecord {
  detail: RectifiableRecordDetail
  rectificacion: RectifiedRecord['rectificacion'] | null
}

/**
 * Carla: el 9961 ya está rectificado, y el 9962 y el 9964 siguen activos. María tiene el 9963 activo; Luis
 * y Daniel no tienen registros.
 */
export function initialRecords(): StubbedRecord[] {
  return [
    {
      detail: detail(carlaCondori, 9961, '2026-09-11T19:00:00+00:00', '1er Parcial', 3, 'Material no permitido'),
      rectificacion: {
        fecha: '2026-10-05T14:00:00+00:00',
        administrador: { nombre_completo: ADMIN_NAME },
        motivo: VOIDED_REASON,
      },
    },
    {
      detail: detail(carlaCondori, 9962, '2026-09-22T14:00:00+00:00', '2do Parcial', 4, 'Conducta indebida', {
        evidencia: { nombre_archivo: 'foto-aula.png', tamano_bytes: 2048, disponible: true },
      }),
      rectificacion: null,
    },
    {
      detail: detail(carlaCondori, 9964, '2026-10-01T15:30:00+00:00', '3er Parcial', 1, 'Copia en examen', {
        ambiente: null,
        descripcion: null,
      }),
      rectificacion: null,
    },
    {
      detail: detail(mariaPerez, 9963, '2026-09-12T13:00:00+00:00', '1er Parcial', 1, 'Copia en examen'),
      rectificacion: null,
    },
  ]
}

function toSummary(record: RectifiableRecordDetail): RectifiableRecord {
  return {
    id_cr_estudiante: record.id_cr_estudiante,
    fecha_registro: record.fecha_registro,
    examen: {
      id_examen: record.examen.id_examen,
      nombre: record.examen.nombre,
      materia: record.examen.materia,
      fecha: record.examen.fecha,
    },
    tipo_falta: record.tipo_falta,
    aprobador: record.aprobador,
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
  /** Otra persona rectificó antes: toda rectificación responde 409. */
  conflict?: boolean
}

export interface RectificationApi {
  calls: RecordedRequest[]
  records: Map<number, StubbedRecord>
  /** Los POST de rectificación recibidos. */
  posts: () => RecordedRequest[]
}

export function stubRectificationApi(options: StubOptions = {}): RectificationApi {
  const students = options.students ?? allStudents
  const records = new Map(initialRecords().map((record) => [record.detail.id_cr_estudiante, record]))
  const notFound = { status: 404, body: { message: 'No existe el registro de la Central de Riesgo indicado.' } }

  const api = mockApiWith((request) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

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

    const studentRecords = path.match(/^\/rectificacion-riesgo\/estudiantes\/(\d+)\/registros$/)

    if (studentRecords) {
      const student = students.find((candidate) => candidate.id_estudiante === Number(studentRecords[1]))

      if (!student) return { status: 404, body: { message: 'No existe el estudiante indicado.' } }

      const own = [...records.values()].filter((record) => record.detail.estudiante.id_estudiante === student.id_estudiante)
      const active = own
        .filter((record) => record.rectificacion === null)
        .sort((left, right) => right.detail.fecha_registro.localeCompare(left.detail.fecha_registro))
        .map((record) => toSummary(record.detail))
      const rectified = own
        .filter((record) => record.rectificacion !== null)
        .sort((left, right) => right.rectificacion!.fecha.localeCompare(left.rectificacion!.fecha))
        .map((record): RectifiedRecord => ({ ...toSummary(record.detail), rectificacion: record.rectificacion! }))

      return { body: { data: { estudiante: student, registros: active, rectificados: rectified } } }
    }

    const rectify = path.match(/^\/rectificacion-riesgo\/registros\/(\d+)\/rectificar$/)

    if (rectify && request.method === 'POST') {
      const record = records.get(Number(rectify[1]))

      if (!record) return notFound

      if (options.conflict || record.rectificacion !== null) {
        return { status: 409, body: { message: 'Este registro ya fue rectificado y ya no está en la Central de Riesgo.' } }
      }

      record.rectificacion = {
        fecha: RECTIFIED_AT,
        administrador: { nombre_completo: ADMIN_NAME },
        motivo: (request.body as { motivo: string }).motivo,
      }

      return { status: 200, body: { data: { ...toSummary(record.detail), rectificacion: record.rectificacion } } }
    }

    const show = path.match(/^\/rectificacion-riesgo\/registros\/(\d+)$/)

    if (show) {
      const record = records.get(Number(show[1]))

      return record && record.rectificacion === null ? { body: { data: record.detail } } : notFound
    }

    return undefined
  })

  return {
    calls: api.calls,
    records,
    posts: () => api.calls.filter((call) => call.method === 'POST'),
  }
}
