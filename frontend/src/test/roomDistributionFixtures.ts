import type {
  DistributionExamOption, DistributionProposal, RoomDistributionView,
} from '@/features/exams/types/roomDistribution.types'

export const DISTRIBUTION_INITIAL_VERSION = '97d170e1550eee4afc0af065b78cda302a97674c'
export const DISTRIBUTION_SAVED_VERSION = 'f7eaf845e782bb110d0696162f07f3a0fe1b12e9'

export function distributionExamFixture(overrides: Partial<DistributionExamOption> = {}): DistributionExamOption {
  return {
    id_examen: 12, nombre: 'Primer parcial', materia: { nombre: 'Cálculo I' },
    fecha: '2026-12-01', hora_inicio: '10:00:00', estado: 'PROGRAMADO', cantidad_ambientes: 2, es_creador: true,
    editable: true, distribucion_suspendida: false, puede_dejar_de_usar: false,
    ...overrides,
  }
}

export function distributionFixture(overrides: Partial<RoomDistributionView> = {}): RoomDistributionView {
  return {
    examen: distributionExamFixture(), editable: true, motivo_solo_lectura: null,
    version: DISTRIBUTION_INITIAL_VERSION, total_habilitados: 4, total_no_habilitados: 2,
    capacidad_total: 6, faltan_asientos: 0,
    ambientes: [
      { id_ambiente: 10, nro_aula: '691A', capacidad: 3, asignados: 0 },
      { id_ambiente: 20, nro_aula: '691B', capacidad: 3, asignados: 0 },
    ],
    estudiantes: [
      { id_estudiante: 1, codigo_sis: '001000001', nombre_completo: 'Álvarez Ana', id_ambiente: null },
      { id_estudiante: 2, codigo_sis: '001000002', nombre_completo: 'Gutiérrez Andrea', id_ambiente: null },
      { id_estudiante: 3, codigo_sis: '001000003', nombre_completo: 'Muñoz José', id_ambiente: null },
      { id_estudiante: 4, codigo_sis: '001000004', nombre_completo: 'Zapata María', id_ambiente: null },
    ],
    ...overrides,
  }
}

export function distributionProposalFixture(): DistributionProposal {
  const destinations = [10, 10, 20, 20]
  return {
    estudiantes: distributionFixture().estudiantes.map((student, index) => ({
      ...student, id_ambiente: destinations[index] ?? null,
    })),
    sin_asignar: 0,
  }
}

export function savedDistributionFixture(overrides: Partial<RoomDistributionView> = {}): RoomDistributionView {
  return distributionFixture({
    version: DISTRIBUTION_SAVED_VERSION,
    estudiantes: distributionProposalFixture().estudiantes,
    ambientes: distributionFixture().ambientes.map((room) => ({ ...room, asignados: 2 })),
    ...overrides,
  })
}
