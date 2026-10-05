import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import {
  makeGroup,
  makeRosterFile,
  makeRosterRow,
  rosterConfirmationResponse,
  rosterPreviewResponse,
} from '@/test/fixtures'
import { renderWithRouter } from '@/test/render'

import { RosterUploadPanel } from './RosterUploadPanel'

/**
 * HU-21: el recorrido completo del docente en el panel de nómina, con la API simulada.
 *
 * Archivo (CA 1-2) → procesando (CA 3) → previsualización (CA 4-5) → revisión de
 * inconsistencias (CA 6-9) → confirmación explícita (CA 12) → resultado (CA 17), y las
 * salidas: cancelar (CA 10-11) y errores claros (CA 15).
 */

interface Reply {
  status?: number
  body: unknown
}

interface Call {
  url: string
  init: RequestInit
}

/** Simula la API de nómina; el preview espera a que la prueba lo libere, para ver «Procesando». */
function stubRosterApi(options: { confirm?: Reply } = {}) {
  const calls: Call[] = []
  let releasePreview: (reply: Reply) => void = () => undefined
  const previewGate = new Promise<Reply>((resolve) => {
    releasePreview = resolve
  })

  const respond = ({ status = 200, body }: Reply): Response =>
    ({
      ok: status >= 200 && status < 300,
      status,
      headers: new Headers(),
      json: async () => body,
    }) as Response

  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
      const url = String(input)
      calls.push({ url, init })

      if (/\/nomina\/preview$/.test(url)) return respond(await previewGate)
      if (/\/nomina\/confirm$/.test(url)) {
        return respond(options.confirm ?? { body: rosterConfirmationResponse() })
      }

      throw new Error(`Petición no esperada en la prueba: ${url}`)
    })
  )

  return {
    calls,
    releasePreview,
    requests: (suffix: string) => calls.filter((call) => call.url.endsWith(suffix)),
  }
}

/** Cinco filas: una nueva, una existente, una ya inscrita y dos inconsistentes. */
const rows = [
  makeRosterRow({ numero_fila: 2, codigo_sis: '300000001', apellidos: 'PEREZ ROJAS', nombres: 'ANA', estado: 'new_student' }),
  makeRosterRow({ numero_fila: 3, codigo_sis: '300000002', apellidos: 'LOPEZ', nombres: 'CARLOS', estado: 'existing_student' }),
  makeRosterRow({ numero_fila: 4, codigo_sis: '300000003', apellidos: 'RIOS', nombres: 'LUIS', estado: 'already_enrolled' }),
  makeRosterRow({ numero_fila: 5, codigo_sis: null, apellidos: 'SIN CODIGO', nombres: 'MARIA', estado: 'inconsistent', errores: ['missing_sis_code'] }),
  makeRosterRow({ numero_fila: 6, codigo_sis: 'ABC12345', apellidos: 'MAL FORMATO', nombres: 'PEDRO', estado: 'inconsistent', errores: ['sis_code_not_numeric'] }),
]

function renderPanel(onReload = vi.fn()) {
  const view = renderWithRouter(
    <RosterUploadPanel group={makeGroup({ id_grupo: 100 })} subjectName="Bases de Datos I" onReload={onReload} />
  )

  return { ...view, onReload }
}

async function selectFileAndPreview(file = makeRosterFile('nomina.csv')) {
  const input = document.querySelector('input[type="file"]') as HTMLInputElement
  await userEvent.upload(input, file)
  await userEvent.click(screen.getByRole('button', { name: 'Previsualizar' }))
}

async function reachPreview(api: ReturnType<typeof stubRosterApi>) {
  await selectFileAndPreview()
  api.releasePreview({ body: rosterPreviewResponse(rows) })
  await screen.findByRole('button', { name: 'Continuar con 2 estudiantes' })
}

describe('RosterUploadPanel · recorrido de la carga', () => {
  it('CA 1-2: el archivo se elige y no se envía hasta pulsar «Previsualizar»', async () => {
    const api = stubRosterApi()
    renderPanel()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    expect(input).toHaveAttribute('accept', '.csv,.xlsx')

    await userEvent.upload(input, makeRosterFile('nomina.xlsx'))

    expect(screen.getByText('nomina.xlsx')).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
    expect(screen.getByRole('button', { name: 'Previsualizar' })).toBeEnabled()
  })

  it('CA 3: mientras se procesa muestra «Procesando» y avisa que la nómina no cambió', async () => {
    const api = stubRosterApi()
    renderPanel()

    await selectFileAndPreview()

    const status = await screen.findByRole('status')
    expect(within(status).getByText('Procesando…')).toBeInTheDocument()
    expect(within(status).getByText('La nómina del grupo todavía no se modificó.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeInTheDocument()

    const [preview] = api.requests('/grupos/100/nomina/preview')
    expect((preview.init.body as FormData).get('archivo')).toBeInstanceOf(File)

    api.releasePreview({ body: rosterPreviewResponse(rows) })
    await screen.findByRole('button', { name: 'Continuar con 2 estudiantes' })
  })

  it('CA 4-5: la previsualización muestra las cifras y todas las filas antes de incorporar nada', async () => {
    const api = stubRosterApi()
    renderPanel()

    await reachPreview(api)

    expect(screen.getByText('Filas leídas')).toBeInTheDocument()
    expect(screen.getByText('Inconsistencias')).toBeInTheDocument()
    expect(screen.getByText('Ningún registro cambia hasta la confirmación.', { exact: false })).toBeInTheDocument()
    for (const apellidos of ['PEREZ ROJAS', 'LOPEZ', 'RIOS', 'SIN CODIGO', 'MAL FORMATO']) {
      expect(screen.getAllByText(apellidos).length).toBeGreaterThan(0)
    }
    expect(screen.getByRole('button', { name: 'Continuar con 2 estudiantes' })).toBeEnabled()
    expect(api.requests('/nomina/confirm')).toHaveLength(0)
  })

  it('CA 6-8: cada inconsistencia dice qué le pasa a la fila', async () => {
    const api = stubRosterApi()
    renderPanel()

    await reachPreview(api)

    expect(screen.getAllByText('Falta el código SIS').length).toBeGreaterThan(0)
    expect(screen.getAllByText('El código SIS debe tener solo dígitos').length).toBeGreaterThan(0)
  })

  it('CA 5: el filtro «Con inconsistencias» deja solo esas filas', async () => {
    const api = stubRosterApi()
    renderPanel()

    await reachPreview(api)
    await userEvent.click(screen.getByRole('button', { name: 'Con inconsistencias' }))

    expect(screen.getByRole('button', { name: 'Con inconsistencias' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getAllByText('SIN CODIGO').length).toBeGreaterThan(0)
    expect(screen.queryByText('PEREZ ROJAS')).not.toBeInTheDocument()
  })

  it('CA 9 y 12: antes de confirmar se revisan las inconsistencias y solo entonces se escribe', async () => {
    const api = stubRosterApi()
    const { onReload } = renderPanel()

    await reachPreview(api)
    await userEvent.click(screen.getByRole('button', { name: 'Continuar con 2 estudiantes' }))

    expect(await screen.findByText('La carga es aditiva')).toBeInTheDocument()
    expect(screen.getByText('Inconsistentes').parentElement?.nextElementSibling).toHaveTextContent('2')
    expect(screen.getByText('La carga es aditiva')).toBeInTheDocument()
    expect(screen.getByText('Ningún otro grupo se modifica.')).toBeInTheDocument()
    expect(api.requests('/nomina/confirm')).toHaveLength(0)

    await userEvent.click(screen.getByRole('button', { name: 'Confirmar importación' }))

    expect(await screen.findByText('Nómina actualizada')).toBeInTheDocument()
    const [confirm] = api.requests('/grupos/100/nomina/confirm')
    expect(JSON.parse(String(confirm.init.body))).toEqual({ token: 'a'.repeat(64) })
    await waitFor(() => expect(onReload).toHaveBeenCalledTimes(1))
  })

  it('CA 17: el resultado muestra cuántos estudiantes quedaron inscritos', async () => {
    const api = stubRosterApi({
      confirm: { body: rosterConfirmationResponse({ estudiantes_creados: 1, estudiantes_inscritos: 2, ya_inscritos: 1 }) },
    })
    renderPanel()

    await reachPreview(api)
    await userEvent.click(screen.getByRole('button', { name: 'Continuar con 2 estudiantes' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Confirmar importación' }))

    expect(await screen.findByText('La nómina fue actualizada')).toBeInTheDocument()
    expect(screen.getByText('2 estudiantes quedaron inscritos en el grupo.', { exact: false })).toBeInTheDocument()
  })

  it('CA 10-11: cancelar en la previsualización vuelve al archivo sin confirmar nada', async () => {
    const api = stubRosterApi()
    const { onReload } = renderPanel()

    await reachPreview(api)
    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByText('Seleccionar archivo')).toBeInTheDocument()
    expect(api.requests('/nomina/confirm')).toHaveLength(0)
    expect(onReload).not.toHaveBeenCalled()
  })

  it('«Volver» desde la confirmación conserva la previsualización', async () => {
    const api = stubRosterApi()
    renderPanel()

    await reachPreview(api)
    await userEvent.click(screen.getByRole('button', { name: 'Continuar con 2 estudiantes' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Volver' }))

    expect(await screen.findByRole('button', { name: 'Continuar con 2 estudiantes' })).toBeInTheDocument()
    expect(api.requests('/nomina/confirm')).toHaveLength(0)
  })

  it('CA 15: un archivo de más de 10 MB se rechaza al instante, en castellano y sin subirlo', async () => {
    const api = stubRosterApi()
    renderPanel()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, makeRosterFile('grande.csv', 11 * 1024 * 1024))

    expect(await screen.findByText('La nómina no puede superar los 10 MB.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Previsualizar' })).toBeDisabled()
    expect(api.calls).toHaveLength(0)
  })

  it('CA 15: un archivo vacío o con otra extensión se rechaza sin subirlo', async () => {
    const api = stubRosterApi()
    renderPanel()

    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    await userEvent.upload(input, makeRosterFile('vacia.csv', 0))
    expect(await screen.findByText('El archivo está vacío.')).toBeInTheDocument()

    await userEvent.upload(input, makeRosterFile('nomina.csv', 1024))
    expect(screen.queryByText('El archivo está vacío.')).not.toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })

  it.each([
    [422, 'La nómina supera el máximo de 2000 filas por archivo.', { message: 'La nómina supera el máximo de 2000 filas por archivo.' }],
    [422, 'El archivo CSV no es un archivo de texto.', { message: 'El archivo CSV no es un archivo de texto. Guárdelo como CSV con codificación UTF-8 y vuelva a cargarlo.' }],
    [413, 'La nómina no puede superar los 10 MB.', { message: 'La nómina no puede superar los 10 MB.' }],
    [403, 'Solo un docente puede cargar la nómina de un grupo.', { message: 'Solo un docente puede cargar la nómina de un grupo.' }],
  ])('CA 15: el error %i del servidor se muestra tal cual, en el paso del archivo', async (status, text, body) => {
    const api = stubRosterApi()
    renderPanel()

    await selectFileAndPreview()
    api.releasePreview({ status, body })

    expect(await screen.findByText(text, { exact: false })).toBeInTheDocument()
    expect(screen.getByText('No se pudo procesar la nómina')).toBeInTheDocument()
    expect(api.requests('/nomina/confirm')).toHaveLength(0)
  })
})
