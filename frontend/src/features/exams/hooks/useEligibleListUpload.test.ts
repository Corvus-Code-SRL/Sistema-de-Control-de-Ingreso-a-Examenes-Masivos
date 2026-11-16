import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useEligibleListUpload } from './useEligibleListUpload'
import { eligibleListService } from '../services/eligibleListService'
import type { UploadConfirmData } from '../types/eligibleList.types'

vi.mock('../services/eligibleListService', () => ({
  eligibleListService: {
    previewUpload: vi.fn(),
    confirmUpload: vi.fn(),
  },
  validateEligibleFile: (file: File) => {
    const ext = file.name.split('.').pop()?.toLowerCase()
    if (ext !== 'csv' && ext !== 'xlsx') return 'El archivo debe tener formato CSV o XLSX.'
    if (file.size === 0) return 'El archivo está vacío.'
    if (file.size > 10 * 1024 * 1024) return 'El archivo no puede superar los 10 MB.'
    return null
  },
}))

const previewMock = vi.mocked(eligibleListService.previewUpload)
const confirmMock = vi.mocked(eligibleListService.confirmUpload)

function makeFile(name = 'lista.csv'): File {
  return new File(['codigo_sis\n202210443'], name)
}

describe('useEligibleListUpload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('inicia en idle sin archivo', () => {
    const { result } = renderHook(() => useEligibleListUpload(1))
    expect(result.current.step).toBe('idle')
    expect(result.current.file).toBeNull()
  })

  it('rechaza archivos con extensión inválida', () => {
    const { result } = renderHook(() => useEligibleListUpload(1))
    act(() => result.current.startUpload(new File(['x'], 'lista.pdf')))
    expect(result.current.step).toBe('idle')
    expect(result.current.errorMessage).toMatch(/CSV o XLSX/)
    expect(previewMock).not.toHaveBeenCalled()
  })

  it('hace preview y guarda los datos', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 3,
      filas_validas: 2,
      filas_inconsistentes: 1,
      faltantes: 0,
      filas: [],
    })

    const { result } = renderHook(() => useEligibleListUpload(1))
    await act(async () => {
      result.current.startUpload(makeFile())
    })

    await waitFor(() => expect(result.current.step).toBe('preview'))
    expect(previewMock).toHaveBeenCalledWith(1, expect.any(File), expect.any(AbortSignal))
    expect(result.current.preview?.token).toHaveLength(64)
  })

  it('al confirmar marca result y devuelve los cambios pendientes', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 1,
      filas_validas: 1,
      filas_inconsistentes: 0,
      faltantes: 0,
      filas: [],
    })
    confirmMock.mockResolvedValue({
      total_filas: 1,
      filas_inconsistentes: 0,
      no_encontrados: 0,
      cambios_pendientes: [
        {
          id_estudiante: 1,
          codigo_sis: '202210443',
          estado_habilitacion: 'NO_HABILITADO',
          motivo: null,
          detalle: null,
        },
      ],
    })

    const { result } = renderHook(() => useEligibleListUpload(1))
    await act(async () => {
      result.current.startUpload(makeFile())
    })
    await waitFor(() => expect(result.current.step).toBe('preview'))

    let applied: UploadConfirmData | null = null
    await act(async () => {
      applied = await result.current.applyToPending('ASISTENCIA_INSUFICIENTE')
    })

    expect(applied).not.toBeNull()
    expect(applied!.cambios_pendientes).toHaveLength(1)
    expect(result.current.step).toBe('result')
    expect(result.current.result?.cambios_pendientes).toHaveLength(1)
  })

  it('reset vuelve a idle', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 0,
      filas_validas: 0,
      filas_inconsistentes: 0,
      faltantes: 0,
      filas: [],
    })
    const { result } = renderHook(() => useEligibleListUpload(1))
    await act(async () => {
      result.current.startUpload(makeFile())
    })
    await waitFor(() => expect(result.current.step).toBe('preview'))

    act(() => result.current.reset())
    expect(result.current.step).toBe('idle')
    expect(result.current.preview).toBeNull()
  })
})
