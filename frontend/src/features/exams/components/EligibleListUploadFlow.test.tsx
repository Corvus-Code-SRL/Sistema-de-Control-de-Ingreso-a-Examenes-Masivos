import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
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

function makeFile(): File {
  return new File(['codigo_sis\n202210443'], 'lista.csv')
}

describe('EligibleListUploadFlow', () => {
  beforeEach(() => vi.clearAllMocks())

  it('flujo completo: upload → preview → motivo → aplicar', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 1,
      filas_validas: 1,
      filas_inconsistentes: 0,
      faltantes: 0,
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
      cambios_pendientes: [
        {
          id_estudiante: 1,
          codigo_sis: '202210443',
          nombre_completo: 'Álvarez Rojas, Camila',
          estado_habilitacion: 'NO_HABILITADO',
          motivo: null,
          detalle: null,
        },
      ],
    })

    const onPendingChanges = vi.fn()
    const onClose = vi.fn()
    const user = userEvent.setup({ pointerEventsCheck: 0 })

    render(
      <EligibleListUploadFlow
        open
        examId={1}
        onClose={onClose}
        onPendingChanges={onPendingChanges}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())

    // El preview ya no muestra una tabla con el código SIS; muestra 3 cards
    // con los conteos de coincidentes, no habilitados por no figurar y ajenos.
    await waitFor(() =>
      expect(screen.getByText('Coinciden con la nómina')).toBeInTheDocument()
    )
    expect(screen.getByText('De la nómina no figuran')).toBeInTheDocument()
    expect(screen.getByText('No están en la nómina')).toBeInTheDocument()

    await user.click(
      await screen.findByRole('combobox', { name: /Motivo para los que no figuran/i })
    )
    await user.click(await screen.findByRole('option', { name: /Asistencia insuficiente/i }))

    await user.click(await screen.findByRole('button', { name: /Aplicar a la lista/i }))

    await waitFor(() => {
      expect(onPendingChanges).toHaveBeenCalledWith([
        expect.objectContaining({ id_estudiante: 1, estado_habilitacion: 'NO_HABILITADO' }),
      ])
    })
    // El panel NO se cierra al aplicar: muestra el resumen.
    expect(onClose).not.toHaveBeenCalled()

    // El resumen lista al no habilitado con nombre y SIS.
    expect(await screen.findByText('Quedarán no habilitados')).toBeInTheDocument()
    expect(screen.getByText('202210443')).toBeInTheDocument()
    expect(screen.getByText('Álvarez Rojas, Camila')).toBeInTheDocument()

    // "Listo" cierra el panel.
    await user.click(screen.getByRole('button', { name: /Listo/i }))
    expect(onClose).toHaveBeenCalled()
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
        onClose={vi.fn()}
        onPendingChanges={vi.fn()}
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

  it('exige detalle cuando el motivo es OTRO', async () => {
    previewMock.mockResolvedValue({
      token: 't'.repeat(64),
      total_filas: 0,
      filas_validas: 0,
      filas_inconsistentes: 0,
      faltantes: 0,
      filas: [],
    })

    const user = userEvent.setup({ pointerEventsCheck: 0 })
    render(
      <EligibleListUploadFlow
        open
        examId={1}
        onClose={vi.fn()}
        onPendingChanges={vi.fn()}
      />
    )

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await user.upload(input, makeFile())
    await waitFor(() => expect(previewMock).toHaveBeenCalled())

    await user.click(
      await screen.findByRole('combobox', { name: /Motivo para los que no figuran/i })
    )
    await user.click(await screen.findByRole('option', { name: /Otro/i }))

    await user.click(await screen.findByRole('button', { name: /Aplicar a la lista/i }))
    expect(await screen.findByText(/al menos 10 caracteres/i)).toBeInTheDocument()
    expect(confirmMock).not.toHaveBeenCalled()
  })
})
