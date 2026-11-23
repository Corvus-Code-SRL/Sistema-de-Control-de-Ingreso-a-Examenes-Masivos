import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import type { EntryRoomWarning } from '../types/entry-control.types'
import { ROOM_WARNING_VERDICT, roomWarningsOf } from '../utils/entryRoomWarning'
import { useEntryRoomWarning } from './useEntryRoomWarning'

const WARNING: EntryRoomWarning = { tipo: 'FUERA_DE_AMBIENTE', mensaje: 'Este estudiante tiene asignado el ambiente 691A' }
const warningError = () => new ApiError(409, 'Confirme', {}, undefined, {
  responseBody: { veredicto: ROOM_WARNING_VERDICT, avisos_ambiente: [WARNING] },
})

describe('avisos de ambiente', () => {
  it('reconoce solo el 409 con avisos', () => {
    expect(roomWarningsOf(warningError())).toEqual([WARNING])
    expect(roomWarningsOf(new ApiError(409, 'Duplicado'))).toBeNull()
    expect(roomWarningsOf(new Error('red'))).toBeNull()
  })

  it('guarda el aviso y repite la acción una sola vez al confirmar', () => {
    const retry = vi.fn()
    const { result } = renderHook(() => useEntryRoomWarning())

    act(() => { expect(result.current.capture(warningError(), retry)).toBe(true) })
    expect(result.current.warnings).toEqual([WARNING])
    act(() => result.current.confirm())

    expect(retry).toHaveBeenCalledTimes(1)
    expect(result.current.warnings).toBeNull()
  })

  it('cancelar no repite la acción y otro error no se captura', () => {
    const retry = vi.fn()
    const { result } = renderHook(() => useEntryRoomWarning())

    act(() => { expect(result.current.capture(new ApiError(409, 'Duplicado'), retry)).toBe(false) })
    act(() => { result.current.capture(warningError(), retry) })
    act(() => result.current.cancel())

    expect(retry).not.toHaveBeenCalled()
    expect(result.current.warnings).toBeNull()
  })
})
