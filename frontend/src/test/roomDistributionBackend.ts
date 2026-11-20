import { meBody, TOKEN_STORAGE_KEY, type AccountKey } from './authFixtures'
import { mockApiWith } from './http'
import { distributionExamFixture, distributionFixture, distributionProposalFixture, savedDistributionFixture } from './roomDistributionFixtures'
import type { DistributionExamOption, RoomDistributionView } from '@/features/exams/types/roomDistribution.types'

interface ResponseFixture { status?: number; body: unknown }
export function mockDistributionBackend(options: {
  role?: AccountKey | 'invitado' | null
  exams?: DistributionExamOption[]
  view?: (id: number) => RoomDistributionView
  getError?: number
  save?: () => ResponseFixture
  proposal?: () => ResponseFixture
} = {}) {
  const role = options.role === undefined ? 'docente' : options.role
  if (role !== null) window.localStorage.setItem(TOKEN_STORAGE_KEY, 'distribution-test-session')
  return mockApiWith(({ url, method }) => {
    if (url.endsWith('/auth/yo')) {
      if (role === null) return { status: 401, body: { message: 'Unauthenticated.' } }
      const session = meBody(role === 'invitado' ? 'docente' : role)
      if (role === 'invitado') session.data.rol = { id_rol: 4, nombre_rol: 'Invitado' }
      return { body: session }
    }
    if (url.endsWith('/materias')) return { body: {
      data: [], meta: { total: 0, total_mias: 0, id_periodo_activo: 1 },
    } }
    if (url.endsWith('/examenes/distribucion')) return { body: { data: options.exams ?? [distributionExamFixture()] } }
    if (url.endsWith('/distribucion/propuesta')) {
      return options.proposal?.() ?? { body: { data: distributionProposalFixture() } }
    }
    if (/\/examenes\/\d+\/distribucion$/.test(url)) {
      if (method === 'PUT') return options.save?.() ?? { body: { data: savedDistributionFixture(), mensaje: 'Distribución guardada' } }
      if (options.getError) return { status: options.getError, body: { message: 'No se puede consultar esta distribución.' } }
      const id = Number(url.match(/examenes\/(\d+)\//)?.[1] ?? 12)
      return { body: { data: options.view?.(id) ?? distributionFixture({
        examen: distributionExamFixture({ id_examen: id }),
      }) } }
    }
    return { body: { data: [] } }
  })
}
