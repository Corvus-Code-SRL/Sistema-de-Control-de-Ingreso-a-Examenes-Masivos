import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { configureSessionHandlers } from '@/lib/api-client'
import { detailOf, stubRiskCenterApi } from '@/test/riskCenterFixtures'
import { RiskCenterDetailPanel } from './RiskCenterDetailPanel'

const EDIT_OR_DELETE = /editar|eliminar|borrar|rectificar|modificar/i

function renderPanel(id = 9945, onClose = vi.fn()) {
  render(
    <MemoryRouter>
      <RiskCenterDetailPanel id={id} onClose={onClose} />
    </MemoryRouter>
  )

  return { onClose }
}

async function openedDialog() {
  const dialog = await screen.findByRole('dialog', { name: 'Registro de la Central de Riesgo' })
  await within(dialog).findByText(/Descripción/)

  return dialog
}

afterEach(() => {
  configureSessionHandlers(null)
  vi.unstubAllGlobals()
})

describe('RiskCenterDetailPanel', () => {
  it('muestra la descripción, la evidencia, quién reportó, quién aprobó, la observación y las fechas', async () => {
    stubRiskCenterApi()

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).getByText('José Luis Pérez Núñez')).toBeInTheDocument()
    expect(within(dialog).getByText('202312345')).toBeInTheDocument()
    expect(within(dialog).getByText('Suplantación de identidad')).toBeInTheDocument()
    expect(within(dialog).getByText('1er Parcial · Cálculo I · Ing. de Sistemas · 20/10/2026')).toBeInTheDocument()
    expect(within(dialog).getByText('Aula 691A · 10:03')).toBeInTheDocument()
    expect(within(dialog).getByText('Daniela Ferrufino Soliz · 20/10 10:05')).toBeInTheDocument()
    expect(within(dialog).getByText('Marcelo Quiroga Andrade · 20/10 10:21')).toBeInTheDocument()
    expect(within(dialog).getByText('Confirmado con el carnet original en la puerta.')).toBeInTheDocument()
    expect(within(dialog).getByText('20/10/2026')).toBeInTheDocument()
    expect(
      within(dialog).getByText('El CI presentado (1234567) no coincide con el registrado.')
    ).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: /foto_carnet\.jpg/ })).toBeInTheDocument()
    expect(within(dialog).getByText('Evidencia · 1,2 MB')).toBeInTheDocument()
  })

  it('es de solo lectura: lo único que ofrece es abrir la evidencia y cerrar', async () => {
    stubRiskCenterApi()

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).queryByRole('button', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(within(dialog).queryByRole('link')).not.toBeInTheDocument()
    expect(within(dialog).queryByRole('textbox')).not.toBeInTheDocument()
    expect(
      within(dialog).getAllByRole('button').map((button) => button.getAttribute('aria-label') ?? button.textContent)
    ).toEqual(['Cerrar detalle', expect.stringContaining('foto_carnet.jpg'), 'Cerrar'])
    expect(within(dialog).getByText('Los registros no se editan ni se eliminan.')).toBeInTheDocument()
    expect(within(dialog).getByText('Solo lectura')).toBeInTheDocument()
    expect(dialog.textContent).not.toMatch(/HU-/)
  })

  it('sin intento de ingreso no muestra el ambiente', async () => {
    stubRiskCenterApi({ details: { 9945: detailOf(9945, { ambiente: null }) } })

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).queryByText('Ambiente')).not.toBeInTheDocument()
    expect(within(dialog).queryByText(/Aula/)).not.toBeInTheDocument()
  })

  it('sin evidencia no muestra el enlace ni el apartado', async () => {
    stubRiskCenterApi({ details: { 9945: detailOf(9945, { evidencia: null }) } })

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).queryByText('Evidencia', { selector: 'dt' })).not.toBeInTheDocument()
    expect(within(dialog).queryByRole('button', { name: /foto_carnet/ })).not.toBeInTheDocument()
  })

  it('sin tamaño disponible muestra solo el nombre del archivo', async () => {
    stubRiskCenterApi({
      details: {
        9945: detailOf(9945, { evidencia: { nombre_archivo: 'acta.pdf', tamano_bytes: null, disponible: true } }),
      },
    })

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).getByRole('button', { name: /acta\.pdf/ })).toBeInTheDocument()
    expect(within(dialog).getByText('Evidencia', { selector: 'p' })).toBeInTheDocument()
  })

  it('si el archivo falta lo avisa y no ofrece abrirlo', async () => {
    stubRiskCenterApi({
      details: {
        9945: detailOf(9945, { evidencia: { nombre_archivo: 'perdida.png', tamano_bytes: null, disponible: false } }),
      },
    })

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).getByText('El archivo de la evidencia no está disponible.')).toBeInTheDocument()
    expect(within(dialog).queryByRole('button', { name: /perdida\.png/ })).not.toBeInTheDocument()
  })

  it('la carrera es la del examen: no aparece junto al estudiante y se omite de la línea del examen si falta', async () => {
    stubRiskCenterApi({
      details: {
        9945: detailOf(9945, {
          examen: { id_examen: 1, nombre: '1er Parcial', materia: 'Cálculo I', carrera: null, fecha: '2026-10-20' },
          observacion: null,
          descripcion: null,
        }),
      },
    })

    renderPanel()
    const dialog = await openedDialog()

    expect(within(dialog).getByText('1er Parcial · Cálculo I · 20/10/2026')).toBeInTheDocument()
    expect(within(dialog).getByText('202312345')).toBeInTheDocument()
    expect(dialog.textContent).not.toContain('—')
    expect(dialog.textContent).not.toContain('Ing. de Sistemas')
    expect(within(dialog).getByText('Sin observación')).toBeInTheDocument()
    expect(within(dialog).getByText('Sin descripción')).toBeInTheDocument()
  })

  it('un registro inexistente muestra «Registro no encontrado»', async () => {
    stubRiskCenterApi()

    renderPanel(123)

    expect(await screen.findByText('Registro no encontrado')).toBeInTheDocument()
  })

  it('un fallo del servidor ofrece reintentar', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: false,
        status: 500,
        headers: new Headers(),
        json: async () => ({ message: 'Error del servidor.' }),
      }))
    )

    renderPanel()

    expect(await screen.findByRole('alert')).toHaveTextContent('Error del servidor.')
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('«Cerrar» y la X avisan para volver al listado', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    const { onClose } = renderPanel()
    const dialog = await openedDialog()

    await user.click(within(dialog).getByRole('button', { name: 'Cerrar' }))
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})

describe('RiskCenterDetailPanel — evidencia', () => {
  let click: ReturnType<typeof vi.spyOn>
  let opened: Array<{ href: string; target: string; download: string }>

  beforeEach(() => {
    opened = []
    click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      opened.push({ href: this.href, target: this.target, download: this.download })
    })
    URL.createObjectURL = vi.fn(() => 'blob:evidencia-1')
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    click.mockRestore()
  })

  it('la pide con el token, no con la URL pública, y abre una imagen en otra pestaña', async () => {
    const user = userEvent.setup()
    configureSessionHandlers({ getToken: () => 'tok-docente', onSessionExpired: vi.fn() })
    const api = stubRiskCenterApi({ evidence: new Blob(['png'], { type: 'image/png' }) })
    renderPanel()
    const dialog = await openedDialog()

    await user.click(within(dialog).getByRole('button', { name: /foto_carnet\.jpg/ }))

    await waitFor(() => expect(opened).toHaveLength(1))

    const request = api.calls.find((call) => call.url.endsWith('/central-riesgo/9945/evidencia'))

    expect(request?.method).toBe('GET')
    expect(request?.headers.Authorization).toBe('Bearer tok-docente')
    expect(opened[0]).toMatchObject({ href: 'blob:evidencia-1', target: '_blank' })
    expect(api.calls.some((call) => call.url.includes('/storage/'))).toBe(false)
  })

  it('un archivo que no se puede mostrar se descarga con su nombre', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi({ evidence: new Blob(['x'], { type: 'text/plain' }) })
    renderPanel()
    const dialog = await openedDialog()

    await user.click(within(dialog).getByRole('button', { name: /foto_carnet\.jpg/ }))

    await waitFor(() => expect(opened).toHaveLength(1))
    expect(opened[0]).toMatchObject({ download: 'foto_carnet.jpg', target: '' })
  })

  it('si la descarga falla muestra el motivo y se puede volver a intentar', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        const url = String(input)

        if (url.endsWith('/evidencia')) {
          return {
            ok: false,
            status: 404,
            headers: new Headers(),
            json: async () => ({ message: 'El registro no tiene una evidencia disponible.' }),
          }
        }

        return { ok: true, status: 200, headers: new Headers(), json: async () => ({ data: detailOf(9945) }) }
      })
    )
    renderPanel()
    const dialog = await openedDialog()

    await user.click(within(dialog).getByRole('button', { name: /foto_carnet\.jpg/ }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'El registro no tiene una evidencia disponible.'
    )
    expect(opened).toHaveLength(0)
    expect(within(dialog).getByRole('button', { name: /foto_carnet\.jpg/ })).toBeEnabled()
  })
})
