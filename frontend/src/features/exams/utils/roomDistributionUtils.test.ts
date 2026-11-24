import { describe, expect, it } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { distributionFixture, distributionProposalFixture, savedDistributionFixture } from '@/test/roomDistributionFixtures'
import { distributionApiError, distributionAssignments, distributionSummary, parseDistributionExamId, proposalMatchesSnapshot } from './roomDistributionUtils'

describe('resumen local de distribución', () => {
  it('cuenta asignados, sin ambiente, faltantes y cambios sin modificar la fuente', () => {
    const data = distributionFixture()
    const assignments = new Map(distributionAssignments(data.estudiantes))
    assignments.set(1, 10)
    assignments.set(2, 10)
    assignments.set(3, 20)
    const summary = distributionSummary(data, assignments)
    expect(summary).toMatchObject({ assigned: 3, unassigned: 1, missingSeats: 0, changes: 3, complete: false })
    expect(summary.rooms.map((room) => room.asignados)).toEqual([2, 1])
    expect(data.ambientes.map((room) => room.asignados)).toEqual([0, 0])
  })
  it('distingue falta de capacidad, ambiente lleno y distribución completa', () => {
    const data = savedDistributionFixture({ capacidad_total: 3 })
    expect(distributionSummary(data, distributionAssignments(data.estudiantes))).toMatchObject({ missingSeats: 1, complete: false })
    const normal = savedDistributionFixture()
    expect(distributionSummary(normal, distributionAssignments(normal.estudiantes))).toMatchObject({ changes: 0, complete: true })
    const overflow = new Map(normal.estudiantes.map((student) => [student.id_estudiante, 10]))
    expect(distributionSummary(normal, overflow).complete).toBe(false)
  })
  it('volver al ambiente original elimina el cambio pendiente', () => {
    const data = savedDistributionFixture()
    const assignments = new Map(distributionAssignments(data.estudiantes))
    assignments.set(1, 20)
    expect(distributionSummary(data, assignments).changes).toBe(1)
    assignments.set(1, 10)
    expect(distributionSummary(data, assignments).changes).toBe(0)
  })
  it('cero habilitados es una distribución completa y sin cambios', () => {
    expect(distributionSummary(distributionFixture({ total_habilitados: 0, estudiantes: [] }), new Map()))
      .toMatchObject({ complete: true, assigned: 0, unassigned: 0, changes: 0 })
  })
})

describe('propuesta del servidor y selección', () => {
  it('acepta la propuesta y detecta cambios de nómina, ambientes o capacidad', () => {
    const data = distributionFixture()
    const proposal = distributionProposalFixture()
    expect(proposalMatchesSnapshot(data, proposal)).toBe(true)
    expect(proposalMatchesSnapshot(data, { ...proposal, estudiantes: proposal.estudiantes.slice(1) })).toBe(false)
    expect(proposalMatchesSnapshot(data, { ...proposal, sin_asignar: 1 })).toBe(false)
    expect(proposalMatchesSnapshot(data, { ...proposal, estudiantes: proposal.estudiantes.map((s) => ({ ...s, id_ambiente: 99 })) })).toBe(false)
    const smaller = { ...data, ambientes: data.ambientes.map((room) => ({ ...room, capacidad: 1 })) }
    expect(proposalMatchesSnapshot(smaller, proposal)).toBe(false)
  })
  it('no acepta estudiantes repetidos aunque el total coincida', () => {
    const proposal = distributionProposalFixture()
    const first = proposal.estudiantes[0]
    if (!first) throw new Error('Falta el escenario de prueba')
    expect(proposalMatchesSnapshot(distributionFixture(), { ...proposal, estudiantes: [first, first, first, first] })).toBe(false)
  })
  it('acepta una propuesta parcial cuando ya se conoce que falta capacidad', () => {
    const data = distributionFixture({ capacidad_total: 2, faltan_asientos: 2,
      ambientes: distributionFixture().ambientes.map((room) => ({ ...room, capacidad: 1 })),
    })
    const destinations = [10, 20, null, null]
    const proposal = { estudiantes: data.estudiantes.map((student, index) => ({
      ...student, id_ambiente: destinations[index] ?? null,
    })), sin_asignar: 2 }
    expect(proposalMatchesSnapshot(data, proposal)).toBe(true)
    expect(distributionSummary(data, distributionAssignments(proposal.estudiantes)))
      .toMatchObject({ complete: false, assigned: 2, unassigned: 2 })
  })
  it.each([null, '', 'NaN', '-1', '0', '1.5', '1e2', '9007199254740992'])('rechaza el identificador %s', (value) => {
    expect(parseDistributionExamId(value)).toBeNull()
  })
  it('acepta un identificador entero positivo', () => { expect(parseDistributionExamId('12')).toBe(12) })
})

describe('errores de distribución', () => {
  it('conserva los errores 422 por campo', () => {
    const error = new ApiError(422, 'The given data was invalid.', { asignaciones: ['La nómina cambió.'] })
    expect(distributionApiError(error)).toBe(error)
    expect(distributionApiError(error).errors.asignaciones).toEqual(['La nómina cambió.'])
  })
  it('traduce el 404 y los errores técnicos del servidor', () => {
    expect(distributionApiError(new ApiError(404, 'No query results')).message).toContain('No se encontró')
    expect(distributionApiError(new ApiError(500, 'Server Error')).message).toContain('No se pudo completar')
    expect(distributionApiError(new Error('TypeError')).message).toContain('No se pudo completar')
  })
})
