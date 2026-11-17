import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
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

const WINDOW_CLOSED =
  'La lista de habilitados ya no se puede modificar: el control de ingreso del examen ya se abrió.'

function makeFile(name = 'lista.csv'): File {
  return new File(['codigo_sis\n202210443'], name)
}

function previewOf(totalRows: number) {
  return {
    token: 't'.repeat(64),
    total_filas: totalRows,
    filas_validas: totalRows,
    filas_inconsistentes: 0,
    faltantes: 0,
    filas: [],
  }
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

  it('al confirmar reemplaza la lista en una sola llamada y marca result', async () => {
    previewMock.mockResolvedValue(previewOf(1))
    confirmMock.mockResolvedValue({
      total_filas: 1,
      filas_inconsistentes: 0,
      no_encontrados: 0,
      habilitados: 1,
      total_no_habilitados: 1,
      motivo: 'NO_FIGURA_EN_LISTA',
      no_habilitados: [
        { id_estudiante: 1, codigo_sis: '202210443', nombre_completo: 'Álvarez Rojas, Camila' },
      ],
    })

    const { result } = renderHook(() => useEligibleListUpload(1))
    await act(async () => {
      result.current.startUpload(makeFile())
    })
    await waitFor(() => expect(result.current.step).toBe('preview'))

    let replaced: UploadConfirmData | null = null
    await act(async () => {
      replaced = await result.current.confirmReplacement()
    })

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(confirmMock).toHaveBeenCalledWith(1, 't'.repeat(64), expect.any(AbortSignal))
    expect(replaced).not.toBeNull()
    expect(result.current.step).toBe('result')
    expect(result.current.result?.total_no_habilitados).toBe(1)
    expect(result.current.result?.no_habilitados).toHaveLength(1)
  })

  it('si el servidor rechaza el reemplazo vuelve al preview con el mensaje', async () => {
    previewMock.mockResolvedValue(previewOf(1))
    confirmMock.mockRejectedValue(new ApiError(409, WINDOW_CLOSED))

    const { result } = renderHook(() => useEligibleListUpload(1))
    await act(async () => {
      result.current.startUpload(makeFile())
    })
    await waitFor(() => expect(result.current.step).toBe('preview'))

    await act(async () => {
      await result.current.confirmReplacement()
    })

    expect(result.current.step).toBe('preview')
    expect(result.current.result).toBeNull()
    expect(result.current.errorMessage).toBe(WINDOW_CLOSED)
  })

  it('reset vuelve a idle', async () => {
    previewMock.mockResolvedValue(previewOf(0))
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
