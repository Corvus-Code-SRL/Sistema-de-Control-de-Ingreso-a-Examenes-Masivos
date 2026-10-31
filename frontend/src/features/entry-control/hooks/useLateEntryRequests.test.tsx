import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { makeLateEntryRequest } from '@/test/lateEntryFixtures'
import { ENTRY_CONTROL_POLL_MS } from '../constants'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { lateEntryService } from '../services/lateEntryService'
import type { ControllerRole } from '../types/late-entry.types'
import { useLateEntryRequests } from './useLateEntryRequests'

const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
const pending = makeLateEntryRequest()
const approved = makeLateEntryRequest({ estado: 'AUTORIZADA', resolutor: { nombre_completo: 'Ana Rojas' } })
const rejected = makeLateEntryRequest({ estado: 'RECHAZADA', resolutor: { nombre_completo: 'Ana Rojas' } })

async function flush() {
  await act(async () => { await Promise.resolve() })
}

async function advance(ms = ENTRY_CONTROL_POLL_MS) {
  await act(async () => { await vi.advanceTimersByTimeAsync(ms) })
}

async function mount(role: ControllerRole, enabled = true) {
  const hook = renderHook(() => useLateEntryRequests(7, role, enabled))
  await flush()
  return hook
}

beforeEach(() => {
  vi.useFakeTimers()
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })
})

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  if (hidden) Object.defineProperty(document, 'hidden', hidden)
})

describe('useLateEntryRequests', () => {
  it('consulta cada ENTRY_CONTROL_POLL_MS y no consulta con la pestaña oculta', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
    await mount('DOCENTE')
    expect(list).toHaveBeenCalledTimes(1)

    await advance()
    expect(list).toHaveBeenCalledTimes(2)

    Object.defineProperty(document, 'hidden', { configurable: true, value: true })
    await advance()
    expect(list).toHaveBeenCalledTimes(2)
  })

  it('no consulta deshabilitado y cancela la petición al desmontar', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
    await mount('DOCENTE', false)
    expect(list).not.toHaveBeenCalled()

    const hook = await mount('DOCENTE')
    const signal = list.mock.calls[0][1]
    hook.unmount()
    expect(signal?.aborted).toBe(true)
  })

  it('anuncia al docente una solicitud nueva, no las que ya estaban', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
    const hook = await mount('DOCENTE')
    expect(hook.result.current.announcement).toBeNull()

    list.mockResolvedValue([pending])
    await advance()

    expect(hook.result.current.pending).toEqual([pending])
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.newRequest('Lucía Fernández Rojas', '692B'))
  })

  it('anuncia al auxiliar que su solicitud fue autorizada o rechazada', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([pending])
    const hook = await mount('AUXILIAR')

    list.mockResolvedValue([approved])
    await advance()
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.waitingApproved)

    act(() => hook.result.current.clearAnnouncement())
    list.mockResolvedValue([pending])
    await advance()
    list.mockResolvedValue([rejected])
    await advance()
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.waitingRejected)
  })

  it('una solicitud recién enviada se anuncia aunque se resuelva antes de la siguiente consulta', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
    const hook = await mount('AUXILIAR')

    act(() => hook.result.current.track(pending))
    expect(hook.result.current.requests).toEqual([pending])

    list.mockResolvedValue([approved])
    await advance()
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.waitingApproved)
  })

  it('autoriza y rechaza actualizando la lista al instante', async () => {
    vi.spyOn(lateEntryService, 'list').mockResolvedValue([pending])
    vi.spyOn(lateEntryService, 'approve').mockResolvedValue(approved)
    vi.spyOn(lateEntryService, 'reject').mockResolvedValue(rejected)
    const hook = await mount('DOCENTE')

    await act(async () => { await hook.result.current.approve(31) })
    expect(hook.result.current.pending).toEqual([])
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.approved)

    await act(async () => { await hook.result.current.reject(31) })
    expect(hook.result.current.requests).toEqual([rejected])
    expect(hook.result.current.announcement).toBe(LATE_ENTRY_MESSAGES.rejected)
  })

  it('muestra el mensaje del servidor si la acción falla y conserva la solicitud', async () => {
    vi.spyOn(lateEntryService, 'list').mockResolvedValue([pending])
    vi.spyOn(lateEntryService, 'approve').mockRejectedValue(new Error('La solicitud ya fue resuelta.'))
    const hook = await mount('DOCENTE')

    let done = true
    await act(async () => { done = await hook.result.current.approve(31) })

    expect(done).toBe(false)
    expect(hook.result.current.actionError).toBe('La solicitud ya fue resuelta.')
    expect(hook.result.current.pending).toEqual([pending])
  })
})
