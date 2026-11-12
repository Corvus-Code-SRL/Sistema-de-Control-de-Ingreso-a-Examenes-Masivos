import type {
  InvitationDetail,
  InvitationState,
  InvitationSummary,
  InvitationTab,
} from '@/features/invitations/types/invitation.types'
import { mockApiWith, type RecordedRequest } from './http'

/**
 * Escenario de la HU-027 (mockup 27.1–27.3): Marcelo invita al 1er Parcial de Cálculo I con dos ambientes
 * de 30 y los grupos 2 y 5 (55 estudiantes); el invitado tiene los grupos 3 (38), 6 (22) y 7 (sin nómina).
 */

export const firstPartial: InvitationDetail = {
  id_invitacion: 11,
  estado: 'PENDIENTE',
  fecha_invitacion: '2026-10-20T12:12:00+00:00',
  fecha_respuesta: null,
  examen: {
    id_examen: 501,
    nombre_examen: '1er Parcial',
    materia: 'Cálculo I',
    carrera: 'Ing. de Sistemas',
    periodo: '2/2026',
    fecha: '2026-10-20',
    hora_inicio: '10:00:00',
    hora_fin: '11:30:00',
  },
  invitante: { nombre_completo: 'Marcelo Quiroga Andrade' },
  mensaje: 'Tomemos el 1er parcial juntos en el bloque 691.',
  motivo_rechazo: null,
  fecha_sin_efecto: null,
  ambientes: [
    { id_ambiente: 1, nro_aula: '691A', capacidad: 30 },
    { id_ambiente: 2, nro_aula: '691B', capacidad: 30 },
  ],
  grupos_examen: [
    { id_grupo: 2, num_grupo: '2', inscritos: 30 },
    { id_grupo: 5, num_grupo: '5', inscritos: 25 },
  ],
  mis_grupos: [
    { id_grupo: 3, num_grupo: '3', inscritos: 38, seleccionable: true, motivo: null, sumado: false },
    { id_grupo: 6, num_grupo: '6', inscritos: 22, seleccionable: true, motivo: null, sumado: false },
    { id_grupo: 7, num_grupo: '7', inscritos: 0, seleccionable: false, motivo: 'sin nómina', sumado: false },
  ],
}

export const finalExam: InvitationDetail = {
  ...firstPartial,
  id_invitacion: 12,
  fecha_invitacion: '2026-10-19T14:00:00+00:00',
  examen: { ...firstPartial.examen, id_examen: 502, nombre_examen: 'Examen final', fecha: '2026-11-13', hora_inicio: '08:15:00' },
  invitante: { nombre_completo: 'Rosario Salazar Vega' },
  mensaje: null,
}

export const expired: InvitationDetail = {
  ...firstPartial,
  id_invitacion: 13,
  estado: 'SIN_EFECTO',
  examen: { ...firstPartial.examen, id_examen: 503, nombre_examen: 'Examen diagnóstico', fecha: '2026-10-06' },
  invitante: { nombre_completo: 'Luis Torrico Arze' },
  fecha_sin_efecto: '2026-10-06T13:50:00+00:00',
}

export const rejected: InvitationDetail = {
  ...firstPartial,
  id_invitacion: 14,
  estado: 'RECHAZADA',
  fecha_respuesta: '2026-10-21T13:00:00+00:00',
  examen: { ...firstPartial.examen, id_examen: 504, nombre_examen: 'Recuperatorio' },
  invitante: { nombre_completo: 'Gustavo Rocha Pinto' },
  motivo_rechazo: 'Tengo otro examen a la misma hora.',
}

export function accepted(invitation: InvitationDetail, groupIds: number[]): InvitationDetail {
  return {
    ...invitation,
    estado: 'ACEPTADA',
    fecha_respuesta: '2026-10-20T12:40:00+00:00',
    mis_grupos: invitation.mis_grupos.map((group) => ({ ...group, sumado: groupIds.includes(group.id_grupo) })),
  }
}

export function summaryOf(invitation: InvitationDetail): InvitationSummary {
  const { id_invitacion, estado, fecha_invitacion, fecha_respuesta, examen, invitante } = invitation

  return { id_invitacion, estado, fecha_invitacion, fecha_respuesta, examen, invitante }
}

const TAB_STATES: Record<InvitationTab, InvitationState[]> = {
  pendientes: ['PENDIENTE'],
  aceptadas: ['ACEPTADA'],
  cerradas: ['RECHAZADA', 'SIN_EFECTO'],
}

export interface InvitationsBackend {
  calls: RecordedRequest[]
  /** Invitaciones guardadas, por id; las respuestas las modifican como lo haría el servidor. */
  store: Map<number, InvitationDetail>
}

interface BackendOptions {
  /** Respuesta forzada para aceptar o rechazar (p. ej. un 409). */
  acceptResponse?: { status: number; body: unknown }
  rejectResponse?: { status: number; body: unknown }
}

/** Backend en memoria: lista por pestaña con totales, detalle, aceptar y rechazar. */
export function stubInvitationsBackend(
  invitations: InvitationDetail[],
  options: BackendOptions = {}
): InvitationsBackend {
  const store = new Map(invitations.map((invitation) => [invitation.id_invitacion, invitation]))

  const { calls } = mockApiWith(({ url, method, body }) => {
    const path = new URL(url).pathname
    const all = [...store.values()]

    if (method === 'GET' && path.endsWith('/invitaciones')) {
      const tab = (new URL(url).searchParams.get('estado') ?? 'pendientes') as InvitationTab
      const count = (name: InvitationTab) => all.filter((item) => TAB_STATES[name].includes(item.estado)).length

      return {
        body: {
          data: all.filter((item) => TAB_STATES[tab].includes(item.estado)).map(summaryOf),
          meta: {
            total_pendientes: count('pendientes'),
            total_aceptadas: count('aceptadas'),
            total_cerradas: count('cerradas'),
          },
        },
      }
    }

    const match = path.match(/\/invitaciones\/(\d+)(?:\/(aceptar|rechazar))?$/)

    if (!match) return undefined

    const id = Number(match[1])
    const invitation = store.get(id)

    if (!invitation) return { status: 404, body: { message: 'No existe la invitación.' } }

    if (method === 'GET' && !match[2]) {
      return { body: { data: invitation } }
    }

    if (method === 'POST' && match[2] === 'aceptar') {
      if (options.acceptResponse) return options.acceptResponse

      const next = accepted(invitation, (body as { grupos: number[] }).grupos)
      store.set(id, next)

      return { body: { data: next, mensaje: 'Invitación aceptada.' } }
    }

    if (method === 'POST' && match[2] === 'rechazar') {
      if (options.rejectResponse) return options.rejectResponse

      const next: InvitationDetail = {
        ...invitation,
        estado: 'RECHAZADA',
        fecha_respuesta: '2026-10-20T12:45:00+00:00',
        motivo_rechazo: (body as { motivo: string | null }).motivo,
      }
      store.set(id, next)

      return { body: { data: next, mensaje: 'Invitación rechazada.' } }
    }

    return undefined
  })

  return { calls, store }
}
