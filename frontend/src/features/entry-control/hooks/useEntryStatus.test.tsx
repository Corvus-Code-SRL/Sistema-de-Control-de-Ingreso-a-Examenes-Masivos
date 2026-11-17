import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { entryControlService } from '../services/entryControlService'
import type { EntryContext, EntryStatus } from '../types/entry-control.types'
import { useEntryStatus } from './useEntryStatus'

const context: EntryContext = {
  id_examen: 7, nombre_examen: 'Primer parcial', fecha: '2026-09-29',
  hora_inicio: '08:00', hora_fin: '10:00', estado: 'PROGRAMADO',
  materia: 'Bases de Datos I', carrera: 'Ing. de Sistemas', grupos: ['1'],
  rol_controlador: 'DOCENTE', es_creador: true, id_ambiente_asignado: 5,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A' }],
}
const status: EntryStatus = {
  ingresados: 1, pendientes: 1, total: 2, ultimos_ingresos: [], version: 'v1', conectados: [],
}
const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')

async function advance(ms: number) {
  await act(async () => { await vi.advanceTimersByTimeAsync(ms) })
}

async function mount() {
  const hook = renderHook(() => useEntryStatus(7))
  await act(async () => { await Promise.resolve() })
  return hook
}

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  if (hidden) Object.defineProperty(document, 'hidden', hidden)
})

describe('useEntryStatus', () => {
  it('espera 5, 10, 20 y hasta 30 segundos ante fallos de red, y reinicia tras una respuesta', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status').mockResolvedValueOnce(status)
    for (let i = 0; i < 6; i += 1) {
      request.mockRejectedValueOnce(new ApiError(0, 'Sin conexión.'))
    }
    request.mockResolvedValue({ sin_cambios: true, version: status.version })

    const { result } = await mount()
    expect(request).toHaveBeenCalledTimes(1)
    expect(result.current.status).toEqual(status)

    await advance(5000)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(4999)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(3)
    await advance(10000)
    expect(request).toHaveBeenCalledTimes(4)
    await advance(20000)
    expect(request).toHaveBeenCalledTimes(5)
    await advance(30000)
    expect(request).toHaveBeenCalledTimes(6)
    await advance(30000)
    expect(request).toHaveBeenCalledTimes(7)
    await advance(30000)
    expect(request).toHaveBeenCalledTimes(8)
    expect(result.current.status).toEqual(status)
    expect(result.current.statusError).toBeNull()
    await advance(4999)
    expect(request).toHaveBeenCalledTimes(8)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(9)
  })

  it('detiene el sondeo al recibir 409 y conserva el último estado', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status')
      .mockResolvedValueOnce(status)
      .mockRejectedValueOnce(new ApiError(409, 'El control ha terminado.'))

    const { result } = await mount()
    await advance(5000)
    expect(result.current.ended).toBe(true)
    expect(result.current.status).toEqual(status)
    await advance(60000)
    expect(request).toHaveBeenCalledTimes(2)
  })

  it('mantiene el intervalo normal ante un error HTTP que no es de red', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status')
      .mockResolvedValueOnce(status)
      .mockRejectedValueOnce(new ApiError(500, 'Error del servidor.'))
      .mockResolvedValue(status)

    await mount()
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(3)
  })

  it('ante un 429 avisa, espera lo que pide Retry-After y sigue consultando', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status')
      .mockResolvedValueOnce(status)
      .mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 7 }))
      .mockResolvedValue(status)

    const { result } = await mount()
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(2)
    expect(result.current.statusError).toBe('Muchas consultas seguidas, reintentando en 7 s.')
    expect(result.current.status).toEqual(status)

    await advance(6999)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(3)
    expect(result.current.statusError).toBeNull()

    await advance(5000)
    expect(request).toHaveBeenCalledTimes(4)
  })

  it('respeta Retry-After cuando contexto recibe 429', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    const contextRequest = vi.spyOn(entryControlService, 'context')
      .mockResolvedValueOnce(context)
      .mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 12 }))
      .mockResolvedValue(context)
    const statusRequest = vi.spyOn(entryControlService, 'status').mockResolvedValue(status)

    const { result } = await mount()
    await advance(5000)
    expect(contextRequest).toHaveBeenCalledTimes(2)
    expect(statusRequest).toHaveBeenCalledTimes(1)
    expect(result.current.statusError).toBe('Muchas consultas seguidas, reintentando en 12 s.')

    await advance(11999)
    expect(contextRequest).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(contextRequest).toHaveBeenCalledTimes(3)
    expect(statusRequest).toHaveBeenCalledTimes(2)
    expect(result.current.statusError).toBeNull()
  })

  it('mantiene cinco segundos si Retry-After pide menos', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status')
      .mockResolvedValueOnce(status)
      .mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 1 }))
      .mockResolvedValue(status)

    await mount()
    await advance(5000)
    await advance(4999)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(3)
  })

  it('respeta Retry-After aunque supere 30 segundos', async () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.spyOn(entryControlService, 'context').mockResolvedValue(context)
    const request = vi.spyOn(entryControlService, 'status')
      .mockResolvedValueOnce(status)
      .mockRejectedValueOnce(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 60 }))
      .mockResolvedValue(status)

    await mount()
    await advance(5000)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(59999)
    expect(request).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(request).toHaveBeenCalledTimes(3)
  })
})
