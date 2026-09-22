import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useRosterUpload } from './useRosterUpload'
import { MAX_ROSTER_FILE_BYTES } from '../services/rosterService'
import {
  makeRosterFile,
  makeRosterRow,
  rosterConfirmationResponse,
  rosterPreviewResponse,
} from '@/test/fixtures'
import { matchers, mockApi, mockApiOnce, mockNetworkFailure } from '@/test/http'

const GROUP_ID = 300

const filas = [
  makeRosterRow({
    numero_fila: 2,
    estado: 'new_student',
  }),
  makeRosterRow({
    numero_fila: 3,
    codigo_sis: '20260002',
    estado: 'existing_student',
  }),
  makeRosterRow({
    numero_fila: 4,
    codigo_sis: null,
    estado: 'inconsistent',
    errores: ['missing_sis_code'],
  }),
]

/** URLs a las que llegó una petición durante la prueba. */
function requestedUrls(): string[] {
  return vi.mocked(fetch).mock.calls.map(([input]) => String(input))
}

/** Deja el hook en el paso de revisión, con el token ya recibido. */
async function renderAtPreview(token = 'a'.repeat(64)) {
  mockApi([
    {
      matches: matchers.rosterPreview,
      body: rosterPreviewResponse(filas, token),
    },
    {
      matches: matchers.rosterConfirm,
      body: rosterConfirmationResponse(),
    },
  ])

  const view = renderHook(() => useRosterUpload(GROUP_ID))

  act(() => {
    view.result.current.selectFile(makeRosterFile())
  })

  expect(view.result.current.step).toBe('file')
  expect(fetch).not.toHaveBeenCalled()

  act(() => {
    view.result.current.submitPreview()
  })

  await waitFor(() => expect(view.result.current.step).toBe('preview'))

  return view
}

describe('useRosterUpload · selección de archivo', () => {
  it('seleccionar un archivo válido no llama al servidor', () => {
    mockApiOnce({ body: {} })
    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    expect(result.current.step).toBe('file')
    expect(result.current.file?.name).toBe('nomina.csv')
    expect(result.current.errorMessage).toBeNull()
    expect(fetch).not.toHaveBeenCalled()
  })

  it('rechaza una extensión no admitida sin llamar al servidor', () => {
    mockApiOnce({ body: {} })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile('inscritos.pdf'))
    })

    expect(result.current.step).toBe('file')
    expect(result.current.file).toBeNull()
    expect(result.current.errorMessage).toBe(
      'El archivo debe tener formato CSV o XLSX.'
    )
    expect(fetch).not.toHaveBeenCalled()
  })

  it('rechaza un archivo vacío sin llamar al servidor', () => {
    mockApiOnce({ body: {} })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile('nomina.csv', 0))
    })

    expect(result.current.errorMessage).toBe('El archivo está vacío.')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('rechaza un archivo de más de 10 MB sin llamar al servidor', () => {
    mockApiOnce({ body: {} })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(
        makeRosterFile('nomina.xlsx', MAX_ROSTER_FILE_BYTES + 1)
      )
    })

    expect(result.current.errorMessage).toBe(
      'La nómina no puede superar los 10 MB.'
    )
    expect(fetch).not.toHaveBeenCalled()
  })
})

describe('useRosterUpload · previsualización', () => {
  it('envía el archivo solo al solicitar la previsualización', async () => {
    mockApi([
      {
        matches: matchers.rosterPreview,
        body: rosterPreviewResponse(filas),
      },
    ])

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    expect(result.current.step).toBe('file')
    expect(fetch).not.toHaveBeenCalled()

    act(() => {
      result.current.submitPreview()
    })

    expect(result.current.step).toBe('processing')

    await waitFor(() => expect(result.current.step).toBe('preview'))

    expect(result.current.preview?.filas).toHaveLength(3)
    expect(requestedUrls()).toEqual([
      expect.stringContaining(`/grupos/${GROUP_ID}/nomina/preview`),
    ])
  })

  it('muestra el campo rechazado de un 422 y no el mensaje en inglés', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'The given data was invalid.',
        errors: {
          archivo: ['La nómina no puede superar los 10 MB.'],
        },
      },
    })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    act(() => {
      result.current.submitPreview()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.errorMessage).toBe(
      'La nómina no puede superar los 10 MB.'
    )
  })

  it('un 422 sin campos rechazados aclara que la nómina no cambió', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'Falta la columna requerida: apellidos.',
      },
    })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    act(() => {
      result.current.submitPreview()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.errorMessage).toBe(
      'Falta la columna requerida: apellidos. La nómina del grupo no se modificó.'
    )
  })

  it('un 404 no filtra el mensaje de Eloquent', async () => {
    mockApiOnce({
      status: 404,
      body: {
        message: 'No query results for model [App\\Models\\Group] 999999',
      },
    })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    act(() => {
      result.current.submitPreview()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.errorMessage).toBe(
      'El grupo no existe o fue dado de baja.'
    )
    expect(result.current.errorMessage).not.toMatch(/App\\Models/)
  })

  it('una caída de red permite reintentar con el mismo archivo', async () => {
    mockNetworkFailure()

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    act(() => {
      result.current.submitPreview()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.canRetry).toBe(true)
    expect(result.current.errorMessage).toMatch(/no se pudo conectar/i)

    mockApi([
      {
        matches: matchers.rosterPreview,
        body: rosterPreviewResponse(filas),
      },
    ])

    act(() => {
      result.current.retry()
    })

    await waitFor(() => expect(result.current.step).toBe('preview'))
  })

  it('un 422 no ofrece reintentar', async () => {
    mockApiOnce({
      status: 422,
      body: {
        message: 'La nómina no contiene estudiantes.',
      },
    })

    const { result } = renderHook(() => useRosterUpload(GROUP_ID))

    act(() => {
      result.current.selectFile(makeRosterFile())
    })

    act(() => {
      result.current.submitPreview()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.canRetry).toBe(false)
  })
})

describe('useRosterUpload · confirmación', () => {
  it('cancelar en la revisión no llama a confirm y descarta el token', async () => {
    const { result } = await renderAtPreview()

    act(() => {
      result.current.reset()
    })

    expect(result.current.step).toBe('file')
    expect(result.current.file).toBeNull()
    expect(result.current.preview).toBeNull()
    expect(
      requestedUrls().some((url) => url.includes('/nomina/confirm'))
    ).toBe(false)
  })

  it('envía el token al mismo grupo del preview y expone las cifras del servidor', async () => {
    const { result } = await renderAtPreview('c'.repeat(64))

    act(() => {
      result.current.goToConfirm()
    })

    expect(result.current.step).toBe('confirm')

    act(() => {
      result.current.confirm()
    })

    await waitFor(() => expect(result.current.step).toBe('result'))

    const confirmCall = vi.mocked(fetch).mock.calls[1]

    expect(String(confirmCall[0])).toContain(
      `/grupos/${GROUP_ID}/nomina/confirm`
    )
    expect(confirmCall[1]?.body).toBe(
      JSON.stringify({ token: 'c'.repeat(64) })
    )
    expect(result.current.result).toEqual({
      total_filas: 3,
      filas_inconsistentes: 1,
      estudiantes_creados: 1,
      estudiantes_inscritos: 2,
      ya_inscritos: 0,
      inscripciones_inactivas: 0,
    })
  })

  it('dos clics seguidos en confirmar envían una sola petición', async () => {
    const { result } = await renderAtPreview()

    act(() => {
      result.current.goToConfirm()
    })

    act(() => {
      result.current.confirm()
      result.current.confirm()
    })

    await waitFor(() => expect(result.current.step).toBe('result'))

    expect(
      requestedUrls().filter((url) => url.includes('/nomina/confirm'))
    ).toHaveLength(1)
  })

  it('un 404 al confirmar vuelve al paso del archivo y suelta el token', async () => {
    const { result } = await renderAtPreview()

    mockApi([
      {
        matches: matchers.rosterConfirm,
        status: 404,
        body: {
          message: 'El preview de la nómina no existe o ha expirado.',
        },
      },
    ])

    act(() => {
      result.current.goToConfirm()
    })

    act(() => {
      result.current.confirm()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.preview).toBeNull()
    expect(result.current.errorMessage).toBe(
      'La previsualización expiró o ya fue confirmada. Vuelva a cargar el archivo.'
    )
  })

  it('un 403 al confirmar vuelve al paso del archivo', async () => {
    const { result } = await renderAtPreview()

    mockApi([
      {
        matches: matchers.rosterConfirm,
        status: 403,
        body: {
          message: 'El preview no pertenece al docente actual.',
        },
      },
    ])

    act(() => {
      result.current.goToConfirm()
    })

    act(() => {
      result.current.confirm()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.errorMessage).toBe(
      'La previsualización no pertenece al docente actual. Vuelva a cargar el archivo.'
    )
  })

  it('un 422 de token ajeno al grupo vuelve al paso del archivo', async () => {
    const { result } = await renderAtPreview()

    mockApi([
      {
        matches: matchers.rosterConfirm,
        status: 422,
        body: {
          message: 'El preview no corresponde al grupo indicado.',
        },
      },
    ])

    act(() => {
      result.current.goToConfirm()
    })

    act(() => {
      result.current.confirm()
    })

    await waitFor(() => expect(result.current.step).toBe('file'))

    expect(result.current.preview).toBeNull()
    expect(result.current.errorMessage).toBe(
      'El preview no corresponde al grupo indicado. Vuelva a cargar el archivo.'
    )
  })

  it('volver desde la confirmación conserva la revisión', async () => {
    const { result } = await renderAtPreview()

    act(() => {
      result.current.goToConfirm()
    })

    expect(result.current.step).toBe('confirm')

    act(() => {
      result.current.backToPreview()
    })

    expect(result.current.step).toBe('preview')
    expect(result.current.preview?.filas).toHaveLength(3)
  })
})