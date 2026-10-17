import { mockApiWith, type RecordedRequest } from '@/test/http'
import type {
  FaultType,
  RiskCenterRecordDetail,
  RiskCenterRecordSummary,
} from '@/features/risk-center/academic/types/riskCenter.types'

/**
 * Datos y servidor de mentira de la Central de Riesgo para las pruebas (HU-046).
 *
 * El servidor imita lo que hace el real: busca sin tildes ni mayúsculas por nombre o código SIS, filtra
 * por tipo, ordena del más reciente al más antiguo y pagina.
 */

export const faultTypes: FaultType[] = [
  { id_falta: 1, nombre: 'Copia en examen' },
  { id_falta: 2, nombre: 'Suplantación de identidad' },
  { id_falta: 3, nombre: 'Material no permitido' },
]

function record(
  id: number,
  name: string,
  sis: string,
  type: FaultType,
  exam: string,
  subject: string,
  registeredAt: string
): RiskCenterRecordSummary {
  return {
    id_cr_estudiante: id,
    estudiante: { id_estudiante: id + 100, nombre_completo: name, cod_sis: sis },
    tipo_falta: type,
    examen: { id_examen: id + 200, nombre: exam, materia: subject },
    fecha_registro: registeredAt,
  }
}

/** Ya en el orden del servidor: del más reciente al más antiguo. */
export const records: RiskCenterRecordSummary[] = [
  record(9945, 'José Luis Pérez Núñez', '202312345', faultTypes[1], '1er Parcial', 'Cálculo I', '2026-10-20T14:21:00+00:00'),
  record(9944, 'Lucía Salazar Terán', '202216630', faultTypes[2], '2do Parcial', 'Física General', '2026-10-14T16:00:00+00:00'),
  record(9943, 'José Luis Pérez Núñez', '202312345', faultTypes[0], '1er Parcial', 'Física General', '2026-06-12T16:00:00+00:00'),
]

export function detailOf(
  id: number,
  overrides: Partial<RiskCenterRecordDetail> = {}
): RiskCenterRecordDetail {
  const summary = records.find((candidate) => candidate.id_cr_estudiante === id) ?? records[0]

  return {
    id_cr_estudiante: summary.id_cr_estudiante,
    estudiante: { ...summary.estudiante },
    tipo_falta: summary.tipo_falta,
    examen: { ...summary.examen, carrera: 'Ing. de Sistemas', fecha: '2026-10-20' },
    ambiente: { nro_aula: '691A', hora_intento: '2026-10-20T14:03:00+00:00' },
    descripcion: 'El CI presentado (1234567) no coincide con el registrado.',
    reportante: { nombre_completo: 'Daniela Ferrufino Soliz', fecha: '2026-10-20T14:05:00+00:00' },
    aprobador: { nombre_completo: 'Marcelo Quiroga Andrade', fecha: '2026-10-20T14:21:00+00:00' },
    observacion: 'Confirmado con el carnet original en la puerta.',
    fecha_registro: summary.fecha_registro,
    evidencia: { nombre_archivo: 'foto_carnet.jpg', tamano_bytes: 1_258_291, disponible: true },
    ...overrides,
  }
}

export function plain(text: string): string {
  return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}

interface StubOptions {
  /** Registros que "existen" en el servidor; por defecto los tres de ejemplo. */
  records?: RiskCenterRecordSummary[]
  /** Detalle por id; por defecto `detailOf`. Un id ausente responde 404. */
  details?: Record<number, RiskCenterRecordDetail>
  types?: FaultType[]
  typesFail?: boolean
  listFail?: boolean
  /** Cuerpo de la evidencia; por defecto un PNG diminuto. */
  evidence?: Blob
}

function paginate(rows: RiskCenterRecordSummary[], url: URL) {
  const perPage = Number(url.searchParams.get('per_page') ?? 10)
  const requested = Number(url.searchParams.get('page') ?? 1)
  const lastPage = Math.max(1, Math.ceil(rows.length / perPage))
  const start = (requested - 1) * perPage

  return {
    data: rows.slice(start, start + perPage),
    meta: { current_page: requested, last_page: lastPage, per_page: perPage, total: rows.length },
  }
}

/** Instala el servidor de mentira y devuelve el registro de peticiones. */
export function stubRiskCenterApi(options: StubOptions = {}): { calls: RecordedRequest[] } {
  const all = options.records ?? records
  const types = options.types ?? faultTypes

  const api = mockApiWith((request) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/tipos-falta') {
      return options.typesFail
        ? { status: 500, body: { message: 'No se pudo cargar el catálogo.' } }
        : { body: { data: types } }
    }

    if (path === '/central-riesgo') {
      if (options.listFail) return { status: 500, body: { message: 'No se pudo cargar la Central de Riesgo.' } }

      const terms = plain(url.searchParams.get('q') ?? '').split(/\s+/).filter(Boolean)
      const type = url.searchParams.get('tipo')

      const rows = all.filter(
        (row) =>
          (type === null || row.tipo_falta.id_falta === Number(type)) &&
          terms.every(
            (term) =>
              plain(row.estudiante.nombre_completo).includes(term) || row.estudiante.cod_sis.includes(term)
          )
      )

      return { body: paginate(rows, url) }
    }

    const evidence = path.match(/^\/central-riesgo\/(\d+)\/evidencia$/)

    if (evidence) {
      return { body: options.evidence ?? new Blob(['png'], { type: 'image/png' }) }
    }

    const detail = path.match(/^\/central-riesgo\/(\d+)$/)

    if (detail) {
      const id = Number(detail[1])
      const found = options.details?.[id] ?? (all.some((row) => row.id_cr_estudiante === id) ? detailOf(id) : null)

      return found
        ? { body: { data: found } }
        : { status: 404, body: { message: 'No existe el registro de la Central de Riesgo indicado.' } }
    }

    return undefined
  })

  return api
}
