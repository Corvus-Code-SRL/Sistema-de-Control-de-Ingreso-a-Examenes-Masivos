import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import {
  distributionExamFixture, distributionFixture, distributionProposalFixture, savedDistributionFixture,
  DISTRIBUTION_INITIAL_VERSION, DISTRIBUTION_SAVED_VERSION,
} from '@/test/roomDistributionFixtures'
import { roomDistributionService } from '../services/roomDistributionService'
import type { RoomDistributionView } from '../types/roomDistribution.types'
import { useRoomDistribution } from './useRoomDistribution'

interface ResponseFixture { status?: number; body: unknown }
function backend(options: {
  view?: (id: number) => RoomDistributionView
  proposal?: () => ResponseFixture
  save?: () => ResponseFixture
} = {}) {
  return mockApiWith(({ url, method }) => {
    if (url.endsWith('/examenes/distribucion')) return { body: { data: [
      distributionExamFixture(), distributionExamFixture({ id_examen: 13, nombre: 'Segundo parcial' }),
    ] } }
    if (url.endsWith('/propuesta')) return options.proposal?.() ?? { body: { data: distributionProposalFixture() } }
    if (method === 'PUT') return options.save?.() ?? { body: { data: savedDistributionFixture() } }
    const id = Number(url.match(/examenes\/(\d+)\//)?.[1] ?? 12)
    return { body: { data: options.view?.(id) ?? distributionFixture({ examen: distributionExamFixture({ id_examen: id }) }) } }
  })
}
function Wrapper({ children }: { children: ReactNode }) {
  return <MemoryRouter initialEntries={['/examenes/distribucion?examen=12']}>{children}</MemoryRouter>
}
async function editor() {
  const hook = renderHook(() => useRoomDistribution(), { wrapper: Wrapper })
  await waitFor(() => expect(hook.result.current.actions.canAct).toBe(true))
  return hook
}
function deferred<T>() {
  let resolve: (value: T) => void = () => { throw new Error('Promesa aún no inicializada') }
  const promise = new Promise<T>((done) => { resolve = done })
  return { promise, resolve }
}
afterEach(() => vi.unstubAllGlobals())

describe('useRoomDistribution', () => {
  it('pide la propuesta al servidor, guarda la versión recibida y solo confirma con PUT', async () => {
    const api = backend()
    const { result } = await editor()
    expect(result.current.isDirty).toBe(false)
    expect(result.current.canSave).toBe(false)
    await act(async () => { await result.current.actions.propose() })
    expect(result.current.summary).toMatchObject({ assigned: 4, unassigned: 0, changes: 4 })
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(0)
    await act(async () => { await result.current.actions.save() })
    expect(api.calls.find((call) => call.method === 'PUT')?.body).toEqual({
      version: DISTRIBUTION_INITIAL_VERSION,
      asignaciones: [{ id_estudiante: 1, id_ambiente: 10 }, { id_estudiante: 2, id_ambiente: 10 },
        { id_estudiante: 3, id_ambiente: 20 }, { id_estudiante: 4, id_ambiente: 20 }],
    })
    expect(result.current.isDirty).toBe(false)
    expect(result.current.data?.version).toBe(DISTRIBUTION_SAVED_VERSION)
    expect(result.current.actions.notice?.title).toBe('Distribución guardada')
  })
  it('valida el aforo incluso ante dos movimientos en el mismo ciclo de React', async () => {
    backend({ view: () => savedDistributionFixture() })
    const { result } = await editor()
    act(() => { result.current.moveStudent(1, 20); result.current.moveStudent(2, 20) })
    expect(result.current.summary?.rooms.map((room) => room.asignados)).toEqual([1, 3])
    expect(result.current.summary?.changes).toBe(1)
    expect(result.current.assignments.get(2)).toBe(10)
    expect(result.current.actions.notice?.title).toBe('El ambiente ya está lleno')
  })
  it('descarta ajustes y restaura la última distribución confirmada', async () => {
    backend({ view: () => savedDistributionFixture() })
    const { result } = await editor()
    act(() => result.current.moveStudent(1, 20))
    expect(result.current.isDirty).toBe(true)
    act(() => result.current.discard())
    expect(result.current.assignments.get(1)).toBe(10)
    expect(result.current.isDirty).toBe(false)
  })
  it.each([409, 422, 500])('un error %i conserva los cambios y nunca reintenta el guardado', async (status) => {
    const api = backend({ save: () => ({ status, body: {
      message: 'La distribución debe revisarse.', errors: status === 422 ? { asignaciones: ['Nómina incompleta.'] } : {},
    } }) })
    const { result } = await editor()
    await act(async () => { await result.current.actions.propose() })
    await act(async () => { await result.current.actions.save() })
    expect(result.current.isDirty).toBe(true)
    expect(result.current.assignments.get(1)).toBe(10)
    expect(result.current.actions.error?.status).toBe(status)
    expect(result.current.actions.mustReload).toBe(status === 409)
    if (status === 422) expect(result.current.actions.error?.errors.asignaciones).toEqual(['Nómina incompleta.'])
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(1)
  })
  it('una propuesta con nómina diferente exige recargar sin reemplazar el borrador', async () => {
    const proposal = distributionProposalFixture()
    backend({ view: () => savedDistributionFixture(), proposal: () => ({ body: { data: {
      ...proposal, estudiantes: proposal.estudiantes.slice(1),
    } } }) })
    const { result } = await editor()
    act(() => result.current.moveStudent(1, 20))
    await act(async () => { await result.current.actions.propose() })
    expect(result.current.assignments.get(1)).toBe(20)
    expect(result.current.actions.mustReload).toBe(true)
  })
  it('cero habilitados permite guardar [] aunque no haya cambios locales', async () => {
    const zero = distributionFixture({ total_habilitados: 0, estudiantes: [], version: DISTRIBUTION_SAVED_VERSION })
    const api = backend({ view: () => zero, save: () => ({ body: { data: { ...zero, version: DISTRIBUTION_INITIAL_VERSION } } }) })
    const { result } = await editor()
    expect(result.current.canSave).toBe(true)
    await act(async () => { await result.current.actions.save() })
    expect(api.calls.find((call) => call.method === 'PUT')?.body).toEqual({ version: DISTRIBUTION_SAVED_VERSION, asignaciones: [] })
  })
  it('una lectura tardía del examen anterior no reemplaza al seleccionado', async () => {
    backend()
    const old = deferred<RoomDistributionView>()
    vi.spyOn(roomDistributionService, 'getDistribution').mockImplementation((id) => id === 12 ? old.promise
      : Promise.resolve(distributionFixture({ examen: distributionExamFixture({ id_examen: id }) })))
    const { result } = renderHook(() => useRoomDistribution(), { wrapper: Wrapper })
    await waitFor(() => expect(result.current.selectedExamId).toBe(12))
    act(() => result.current.selectExam(13))
    await waitFor(() => expect(result.current.data?.examen.id_examen).toBe(13))
    await act(async () => old.resolve(distributionFixture()))
    expect(result.current.data?.examen.id_examen).toBe(13)
  })
  it('no ejecuta dos guardados mientras hay una petición pendiente', async () => {
    backend()
    const pending = deferred<RoomDistributionView>()
    const save = vi.spyOn(roomDistributionService, 'save').mockReturnValue(pending.promise)
    const { result } = await editor()
    await act(async () => { await result.current.actions.propose() })
    act(() => { void result.current.actions.save(); void result.current.actions.save() })
    expect(save).toHaveBeenCalledTimes(1)
    await act(async () => pending.resolve(savedDistributionFixture()))
  })
  it('un conflicto solo se elimina mediante una recarga explícita', async () => {
    let current = distributionFixture()
    const api = backend({ view: () => current, save: () => ({ status: 409, body: { message: 'Versión anterior.' } }) })
    const { result } = await editor()
    await act(async () => { await result.current.actions.propose() })
    await act(async () => { await result.current.actions.save() })
    expect(result.current.actions.mustReload).toBe(true)
    const gets = api.calls.filter((call) => call.method === 'GET').length
    current = savedDistributionFixture()
    act(() => result.current.reload())
    await waitFor(() => expect(result.current.actions.mustReload).toBe(false))
    expect(result.current.data?.version).toBe(DISTRIBUTION_SAVED_VERSION)
    expect(result.current.isDirty).toBe(false)
    expect(api.calls.filter((call) => call.method === 'GET').length).toBe(gets + 1)
  })
})
