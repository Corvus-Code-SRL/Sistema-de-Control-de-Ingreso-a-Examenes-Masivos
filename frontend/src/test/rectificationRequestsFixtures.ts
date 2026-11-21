import { mockApiWith, type RecordedRequest } from '@/test/http'
import type { RectificationRequestCard } from '@/features/risk-center/academic/types/riskRectification.types'

/**
 * Servidor de mentira de la bandeja de solicitudes de rectificación del docente (HU-062).
 *
 * Imita al real: lista las pendientes primero, cuenta las pendientes, acepta (la solicitud pasa a ACEPTADA y
 * el registro queda rectificado) y rechaza (RECHAZADA con el motivo y, si viaja, una imagen). Responder una
 * solicitud ya respondida o vencida da 409.
 */

export const ADMIN = { nombre_completo: 'Administrador del Sistema' }

function card(id: number, overrides: Partial<RectificationRequestCard> = {}): RectificationRequestCard {
  return {
    id_solicitud_rect: id,
    id_cr_estudiante: id + 100,
    estado: 'PENDIENTE',
    fecha_solicitud: '2026-11-20T15:00:00+00:00',
    vence_en: '2999-01-01T15:00:00+00:00',
    fecha_respuesta: null,
    motivo_solicitud: `Se solicita retirar el registro ${id}.`,
    administrador: ADMIN,
    estudiante: { id_estudiante: 9702, nombre_completo: 'Carla Condori Vega', cod_sis: '202170103' },
    examen: { id_examen: 500 + id, nombre: '2do Parcial', materia: 'Cálculo I', fecha: '2026-09-10' },
    hecho: { fecha_hora: '2026-09-10T12:12:00+00:00' },
    tipo_falta: { id_falta: 4, nombre: 'Conducta indebida' },
    descripcion: `Descripción del reporte ${id}.`,
    fecha_registro: '2026-09-22T14:00:00+00:00',
    evidencia: null,
    rechazos_previos: 0,
    registro_rectificado: false,
    respuesta: null,
    ...overrides,
  }
}

/** Dos pendientes (la 9981 con 2 rechazos previos y evidencia), una aceptada, una rechazada con imagen y una vencida. */
export function initialRequests(): RectificationRequestCard[] {
  return [
    card(9980),
    card(9981, {
      rechazos_previos: 2,
      evidencia: { nombre_archivo: 'foto-aula.png', tamano_bytes: 2048, disponible: true },
    }),
    card(9982, { estado: 'ACEPTADA', fecha_respuesta: '2026-11-18T15:00:00+00:00', registro_rectificado: true }),
    card(9983, {
      estado: 'RECHAZADA',
      fecha_respuesta: '2026-11-17T15:00:00+00:00',
      respuesta: {
        motivo: 'El registro está sustentado por dos auxiliares.',
        evidencia: { nombre_archivo: 'rechazo.png', tamano_bytes: 1024, disponible: true },
      },
    }),
    card(9984, { estado: 'VENCIDA', vence_en: '2026-11-10T15:00:00+00:00' }),
  ]
}

interface StubOptions {
  requests?: RectificationRequestCard[]
  /** Toda respuesta (aceptar o rechazar) falla con este estado y mensaje. */
  respondFail?: { status: number; message: string }
  listFail?: boolean
}

export interface RequestsApi {
  calls: RecordedRequest[]
  requests: Map<number, RectificationRequestCard>
  /** Los POST recibidos. */
  posts: () => RecordedRequest[]
}

export function stubRequestsApi(options: StubOptions = {}): RequestsApi {
  const requests = new Map((options.requests ?? initialRequests()).map((request) => [request.id_solicitud_rect, request]))
  const pendingCount = () => [...requests.values()].filter((request) => request.estado === 'PENDIENTE').length

  const api = mockApiWith((request) => {
    const path = new URL(request.url).pathname.replace(/^.*\/api/, '')

    if (path === '/solicitudes-rectificacion' && request.method === 'GET') {
      if (options.listFail) return { status: 500, body: { message: 'No se pudieron cargar las solicitudes.' } }

      return { body: { data: [...requests.values()], meta: { total_pendientes: pendingCount() } } }
    }

    if (path === '/solicitudes-rectificacion/conteo') {
      return { body: { data: { total_pendientes: pendingCount() } } }
    }

    const answer = path.match(/^\/solicitudes-rectificacion\/(\d+)\/(aceptar|rechazar)$/)

    if (answer && request.method === 'POST') {
      const found = requests.get(Number(answer[1]))

      if (!found) return { status: 404, body: { message: 'No existe la solicitud indicada.' } }

      if (options.respondFail) return { status: options.respondFail.status, body: { message: options.respondFail.message } }

      if (found.estado !== 'PENDIENTE') {
        return { status: 409, body: { message: 'Esta solicitud ya fue respondida o venció.' } }
      }

      if (answer[2] === 'aceptar') {
        found.estado = 'ACEPTADA'
        found.registro_rectificado = true
      } else {
        const form = request.body as FormData
        const image = form.get('evidencia')

        found.estado = 'RECHAZADA'
        found.respuesta = {
          motivo: String(form.get('motivo')),
          evidencia:
            image instanceof File ? { nombre_archivo: image.name, tamano_bytes: image.size, disponible: true } : null,
        }
      }

      found.fecha_respuesta = '2026-11-21T15:00:00+00:00'

      return { body: { data: found } }
    }

    return undefined
  })

  return { calls: api.calls, requests, posts: () => api.calls.filter((call) => call.method === 'POST') }
}
