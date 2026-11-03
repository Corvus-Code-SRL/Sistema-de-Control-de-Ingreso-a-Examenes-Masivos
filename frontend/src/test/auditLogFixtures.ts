import { mockApiWith, type RecordedRequest } from '@/test/http'
import type {
  AuditActor,
  AuditChange,
  AuditLogDetail,
  AuditLogEntry,
  AuditLogOptions,
} from '@/features/audit-log/types/auditLog.types'

/**
 * Datos y servidor de mentira de la bitácora para las pruebas (HU-015).
 *
 * El servidor imita lo que hace el real: busca sin tildes ni mayúsculas (coincidencia parcial) por nombre
 * o código SIS, filtra por acción, módulo y rango de días de La Paz, ordena del más reciente al más antiguo
 * y pagina de 50 en 50.
 */

export const options: AuditLogOptions = {
  acciones: [
    { codigo: 'CREAR', etiqueta: 'Crear' },
    { codigo: 'MODIFICAR', etiqueta: 'Modificar' },
    { codigo: 'DESHABILITAR', etiqueta: 'Deshabilitar' },
    { codigo: 'INICIAR', etiqueta: 'Iniciar' },
  ],
  modulos: [
    { clave: 'cuentas', etiqueta: 'Cuentas' },
    { clave: 'grupos', etiqueta: 'Grupos' },
    { clave: 'examenes', etiqueta: 'Exámenes' },
    { clave: 'otros', etiqueta: 'Otros' },
  ],
}

const marcelo: AuditActor = {
  id_usuario: 'u-marcelo',
  nombre_completo: 'Marcelo Quiroga Andrade',
  cod_sis: '10452',
  tipo: 'persona',
  etiqueta: 'Marcelo Quiroga Andrade',
}

const daniela: AuditActor = {
  id_usuario: 'u-daniela',
  nombre_completo: 'Daniela Ferrufino Soliz',
  cod_sis: '20240012',
  tipo: 'persona',
  etiqueta: 'Daniela Ferrufino Soliz',
}

const jose: AuditActor = {
  id_usuario: 'u-jose',
  nombre_completo: 'José Luis Pérez Núñez',
  cod_sis: '202312345',
  tipo: 'persona',
  etiqueta: 'José Luis Pérez Núñez',
}

const automatic: AuditActor = {
  id_usuario: 'u-docente',
  nombre_completo: null,
  cod_sis: null,
  tipo: 'automatico',
  etiqueta: 'Automático',
}

const system: AuditActor = {
  id_usuario: 'u-sistema',
  nombre_completo: null,
  cod_sis: null,
  tipo: 'sistema',
  etiqueta: 'Sistema',
}

function entry(
  id: number,
  at: string,
  usuario: AuditActor,
  accion: [string, string],
  modulo: [string, string],
  registro: [string, string, string],
  automatico = false
): AuditLogEntry {
  return {
    id_log: id,
    fecha_hora: at,
    usuario,
    accion: { codigo: accion[0], etiqueta: accion[1] },
    modulo: { clave: modulo[0], etiqueta: modulo[1] },
    registro: { tabla: registro[0], etiqueta: registro[1], descripcion: registro[2] },
    automatico,
  }
}

/** Ya en el orden del servidor: del más reciente al más antiguo. */
export const entries: AuditLogEntry[] = [
  entry(130, '2026-10-20T14:21:00+00:00', marcelo, ['MODIFICAR', 'Modificar'], ['grupos', 'Grupos'], ['grupo', 'Grupo', 'Grupo #7']),
  // 23:30 del 19/10 en La Paz: ya es 20/10 en UTC.
  entry(129, '2026-10-20T03:30:00+00:00', daniela, ['CREAR', 'Crear'], ['examenes', 'Exámenes'], ['examen', 'Examen', 'Examen #55']),
  entry(128, '2026-10-19T12:00:00+00:00', automatic, ['INICIAR', 'Iniciar'], ['examenes', 'Exámenes'], ['examen', 'Examen', 'Examen #54'], true),
  entry(127, '2026-10-18T16:00:00+00:00', system, ['CREAR', 'Crear'], ['cuentas', 'Cuentas'], ['usuario', 'Cuenta', 'Cuenta: Responsable de Cuentas']),
  entry(126, '2026-10-15T16:00:00+00:00', jose, ['DESHABILITAR', 'Deshabilitar'], ['cuentas', 'Cuentas'], ['usuario', 'Cuenta', 'Cuenta: Lucía Salazar Terán (202216630)']),
]

const GROUP_CHANGES: AuditChange[] = [
  { campo: 'num_grupo', etiqueta: 'Número de grupo', oculto: false, anterior: 3, nuevo: 5 },
]

export function detailOf(id: number, overrides: Partial<AuditLogDetail> = {}): AuditLogDetail {
  const summary = entries.find((candidate) => candidate.id_log === id) ?? entries[0]

  return {
    ...summary,
    registro: { ...summary.registro, ids: { id_grupo: 7 } },
    docente_examen: summary.automatico
      ? { nombre_completo: 'Carlos Rojas Vargas', cod_sis: '202000111' }
      : null,
    cambios: GROUP_CHANGES,
    ...overrides,
  }
}

export function plain(text: string): string {
  return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}

/** Día calendario de La Paz (YYYY-MM-DD) de un instante ISO. */
export function laPazDay(iso: string): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/La_Paz' }).format(new Date(iso))
}

/** Operaciones numeradas de la más reciente a la más antigua, para probar la paginación de 50 en 50. */
export function manyEntries(count: number): AuditLogEntry[] {
  return Array.from({ length: count }, (_, index) => ({
    ...entries[0],
    id_log: 5000 - index,
    fecha_hora: new Date(Date.UTC(2026, 9, 20, 14, 0) - index * 60_000).toISOString(),
    registro: { ...entries[0].registro, descripcion: `Grupo #${index + 1}` },
  }))
}

interface StubOptions {
  /** Operaciones que "existen" en el servidor; por defecto las cinco de ejemplo. */
  entries?: AuditLogEntry[]
  /** Detalle por id; por defecto `detailOf`. Un id ausente responde 404. */
  details?: Record<number, AuditLogDetail>
  options?: AuditLogOptions
  optionsFail?: boolean
  listFail?: boolean
  /** Las peticiones de detalle responden 403. */
  detailForbidden?: boolean
}

const PER_PAGE = 50

function paginate(rows: AuditLogEntry[], url: URL) {
  const requested = Number(url.searchParams.get('page') ?? 1)
  const lastPage = Math.max(1, Math.ceil(rows.length / PER_PAGE))
  const start = (requested - 1) * PER_PAGE

  return {
    data: rows.slice(start, start + PER_PAGE),
    meta: { current_page: requested, last_page: lastPage, per_page: PER_PAGE, total: rows.length },
  }
}

/** Instala el servidor de mentira y devuelve el registro de peticiones. */
export function stubAuditLogApi(config: StubOptions = {}): { calls: RecordedRequest[] } {
  const all = config.entries ?? entries

  return mockApiWith((request) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/bitacora/opciones') {
      return config.optionsFail
        ? { status: 500, body: { message: 'No se pudieron cargar las opciones.' } }
        : { body: { data: config.options ?? options } }
    }

    if (path === '/bitacora') {
      if (config.listFail) return { status: 500, body: { message: 'No se pudo cargar la bitácora.' } }

      const terms = plain(url.searchParams.get('q') ?? '').split(/\s+/).filter(Boolean)
      const action = url.searchParams.get('accion')
      const module = url.searchParams.get('modulo')
      const since = url.searchParams.get('desde')
      const until = url.searchParams.get('hasta')

      const rows = all.filter((row) => {
        const day = laPazDay(row.fecha_hora)

        return (
          (action === null || row.accion.codigo === action) &&
          (module === null || row.modulo.clave === module) &&
          (since === null || day >= since) &&
          (until === null || day <= until) &&
          terms.every(
            (term) =>
              plain(row.usuario.nombre_completo ?? '').includes(term) || (row.usuario.cod_sis ?? '').includes(term)
          )
        )
      })

      return { body: paginate(rows, url) }
    }

    const detail = path.match(/^\/bitacora\/(\d+)$/)

    if (detail) {
      if (config.detailForbidden) {
        return { status: 403, body: { message: 'No tiene permiso para ver esta sección.' } }
      }

      const id = Number(detail[1])
      const found = config.details?.[id] ?? (all.some((row) => row.id_log === id) ? detailOf(id) : null)

      return found
        ? { body: { data: found } }
        : { status: 404, body: { message: 'No existe la operación indicada.' } }
    }

    return undefined
  })
}
