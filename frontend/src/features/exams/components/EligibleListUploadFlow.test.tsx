import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { EligibleListUploadFlow } from './EligibleListUploadFlow'
import { eligibleListService } from '../services/eligibleListService'

vi.mock('../services/eligibleListService', () => ({
  eligibleListService: {
    previewUpload: vi.fn(),
    confirmUpload: vi.fn(),
  },
  validateEligibleFile: (file: File) => {
    const ext = file.name.split('.').pop()?.toLowerCase()
    if (ext !== 'csv' && ext !== 'xlsx') return 'El archivo debe tener formato CSV o XLSX.'
    return null
  },
}))

const previewMock = vi.mocked(eligibleListService.previewUpload)
const confirmMock = vi.mocked(eligibleListService.confirmUpload)

const WINDOW_CLOSED =
  'La lista de habilitados ya no se puede modificar: el control de ingreso del examen ya se abrió.'

function makeFile(): File {
  return new File(['codigo_sis\n202210443'], 'lista.csv')
}

function simplePreview(missing = 0) {
  return {
    token: 't'.repeat(64),
    total_filas: 1,
    filas_validas: 1,
    filas_inconsistentes: 0,
    faltantes: missing,
    filas: [],
  }
}

describe('EligibleListUploadFlow', () => {
  beforeEach(() => vi.clearAllMocks())

  it('flujo completo: archivo → vista previa → reemplazar → resumen del servidor', async () => {
    previewMock.mockResolvedValue({
      ...simplePreview(1),
      filas: [
        {
          numero_fila: 2,
          codigo_sis: '202210443',
          apellidos: 'ALVAREZ',
          nombres: 'CAMILA',
          estado: 'matched',
          errores: [],
        },
      ],
    })
    confirmMock.mockResolvedValue({
      total_filas: 1,
      filas_inconsistentes: 0,
      no_encontrados: 0,
      habilitados: 1,
      total_no_habilitados: 1,
      motivo: 'NO_FIGURA_EN_LISTA',
      no_habilitados: [
        { id_estudiante: 7, codigo_sis: '202210999', nombre_completo: 'Pérez Soto, Luis' },
      ],
    })

    const onReplaced = vi.fn()
    const onClose = vi.fn()
    const user = userEvent.setup({ pointerEventsCheck: 0 })

    render(
      <EligibleListUploadFlow
        open
        examId={1}
        replacesCurrentList={false}
        onClose={onClose}
        onReplaced={onReplaced}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())

    await waitFor(() =>
      expect(screen.getByText('Coinciden con la nómina')).toBeInTheDocument()
    )
    expect(screen.getByText('De la nómina no figuran')).toBeInTheDocument()
    expect(screen.getByText('No están en la nómina')).toBeInTheDocument()

    // El motivo es fijo: no se pide elegirlo y, sin lista previa, no hay aviso de reemplazo.
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getByText(/No figura en la lista cargada/)).toBeInTheDocument()
    expect(screen.queryByText(/Se reemplazará la lista actual/)).not.toBeInTheDocument()

    await user.click(await screen.findByRole('button', { name: /Reemplazar la lista/i }))

    await waitFor(() => expect(onReplaced).toHaveBeenCalledTimes(1))
    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(confirmMock).toHaveBeenCalledWith(1, 't'.repeat(64), expect.any(AbortSignal))
    // El panel NO se cierra al reemplazar: muestra el resumen.
    expect(onClose).not.toHaveBeenCalled()

    // El resumen sale de la respuesta del servidor: nombre y SIS de quien quedó no habilitado.
    expect(await screen.findByText('Quedaron no habilitados')).toBeInTheDocument()
    expect(screen.getByText('202210999')).toBeInTheDocument()
    expect(screen.getByText('Pérez Soto, Luis')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Listo/i }))
    expect(onClose).toHaveBeenCalled()
  })

  it('avisa que se reemplaza la lista actual si hay no habilitados guardados o cambios sin guardar', async () => {
    previewMock.mockResolvedValue(simplePreview())

    const user = userEvent.setup({ pointerEventsCheck: 0 })
    render(
      <EligibleListUploadFlow
        open
        examId={1}
        replacesCurrentList
        onClose={vi.fn()}
        onReplaced={vi.fn()}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())

    expect(
      await screen.findByText('Se reemplazará la lista actual, incluidos los ajustes manuales.')
    ).toBeInTheDocument()
  })

  it('si el servidor rechaza el reemplazo muestra el motivo y no avisa a la página', async () => {
    previewMock.mockResolvedValue(simplePreview())
    confirmMock.mockRejectedValue(new ApiError(409, WINDOW_CLOSED))

    const onReplaced = vi.fn()
    const user = userEvent.setup({ pointerEventsCheck: 0 })
    render(
      <EligibleListUploadFlow
        open
        examId={1}
        replacesCurrentList={false}
        onClose={vi.fn()}
        onReplaced={onReplaced}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())
    await user.click(await screen.findByRole('button', { name: /Reemplazar la lista/i }))

    expect(await screen.findByText(WINDOW_CLOSED)).toBeInTheDocument()
    expect(onReplaced).not.toHaveBeenCalled()
  })

  it('muestra las filas con error y las duplicadas del preview', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 4,
      filas_validas: 1,
      filas_inconsistentes: 3,
      faltantes: 0,
      filas: [
        {
          numero_fila: 3,
          codigo_sis: '202210443',
          apellidos: 'ALVAREZ',
          nombres: 'CAMILA',
          estado: 'inconsistent',
          errores: ['duplicate_row_in_file'],
        },
        {
          numero_fila: 4,
          codigo_sis: '202210443',
          apellidos: 'ALVAREZ',
          nombres: 'OTRA PERSONA',
          estado: 'inconsistent',
          errores: ['conflicting_duplicate_in_file'],
        },
        {
          numero_fila: 5,
          codigo_sis: null,
          apellidos: 'SIN SIS',
          nombres: 'ESTUDIANTE',
          estado: 'inconsistent',
          errores: ['missing_sis_code'],
        },
      ],
    })

    const user = userEvent.setup({ pointerEventsCheck: 0 })
    render(
      <EligibleListUploadFlow
        open
        examId={1}
        replacesCurrentList={false}
        onClose={vi.fn()}
        onReplaced={vi.fn()}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())

    expect(await screen.findByText('Filas con error')).toBeInTheDocument()
    // 2 filas con códigos de duplicado (duplicate_row_in_file + conflicting_duplicate_in_file).
    expect(screen.getByText('2 duplicadas en el archivo')).toBeInTheDocument()
    expect(screen.getByText('código SIS duplicado en el archivo')).toBeInTheDocument()
    expect(screen.getByText('código SIS duplicado con datos distintos')).toBeInTheDocument()
    expect(screen.getByText('sin código SIS')).toBeInTheDocument()
  })
})
